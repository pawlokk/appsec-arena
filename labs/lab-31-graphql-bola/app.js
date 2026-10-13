'use strict';

const express = require('express');
const { graphqlHTTP } = require('express-graphql');
const { buildSchema } = require('graphql');
const Database = require('better-sqlite3');
const jwt = require('jsonwebtoken');
const { execSync } = require('child_process');
const fs = require('fs');

const JWT_SECRET = 'schema_secret';
const DB_PATH = '/tmp/lab31.db';
const PORT = 4000;

// ---------------------------------------------------------------------------
// Database setup
// ---------------------------------------------------------------------------
const db = new Database(DB_PATH);

db.exec(`
  CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    email TEXT NOT NULL,
    apiKey TEXT,
    role TEXT NOT NULL DEFAULT 'user'
  );

  CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    price REAL NOT NULL
  );
`);

// Admin API key is generated from ENV at container startup — never hardcoded
const adminApiKey = process.env.ADMIN_API_KEY || require('crypto').randomBytes(24).toString('hex');
const existingAdmin = db.prepare('SELECT id FROM users WHERE username = ?').get('admin');
if (!existingAdmin) {
  const adminPassword = process.env.ADMIN_PASSWORD || require('crypto').randomBytes(12).toString('hex');
  db.prepare(
    'INSERT INTO users (username, password, email, apiKey, role) VALUES (?, ?, ?, ?, ?)'
  ).run('admin', adminPassword, 'admin@corp.internal', adminApiKey, 'admin');
} else {
  db.prepare('UPDATE users SET apiKey = ? WHERE username = ?').run(adminApiKey, 'admin');
}

// Seed regular users
const existingAlice = db.prepare('SELECT id FROM users WHERE username = ?').get('alice');
if (!existingAlice) {
  db.prepare(
    'INSERT INTO users (username, password, email, apiKey, role) VALUES (?, ?, ?, ?, ?)'
  ).run('alice', 'alice123', 'alice@corp.internal', `key-${require('crypto').randomBytes(8).toString('hex')}`, 'user');
}

const existingBob = db.prepare('SELECT id FROM users WHERE username = ?').get('bob');
if (!existingBob) {
  db.prepare(
    'INSERT INTO users (username, password, email, apiKey, role) VALUES (?, ?, ?, ?, ?)'
  ).run('bob', 'bob456', 'bob@corp.internal', `key-${require('crypto').randomBytes(8).toString('hex')}`, 'user');
}

// Seed products
const productCount = db.prepare('SELECT COUNT(*) as cnt FROM products').get();
if (productCount.cnt === 0) {
  db.prepare('INSERT INTO products (name, price) VALUES (?, ?)').run('Widget Pro', 29.99);
  db.prepare('INSERT INTO products (name, price) VALUES (?, ?)').run('Gadget Plus', 49.99);
  db.prepare('INSERT INTO products (name, price) VALUES (?, ?)').run('SuperTool', 99.99);
}

// ---------------------------------------------------------------------------
// GraphQL schema
// ---------------------------------------------------------------------------
const schema = buildSchema(`
  type User {
    id: ID!
    username: String!
    email: String!
    apiKey: String
    role: String!
    flag: String
  }

  type Product {
    id: ID!
    name: String!
    price: Float!
  }

  type Query {
    me: User
    user(id: ID!): User
    products: [Product]
  }

  type Mutation {
    register(username: String!, password: String!, email: String!): AuthPayload
    login(username: String!, password: String!): AuthPayload
    runDiagnostic(apiKey: String!, cmd: String!): String
  }

  type AuthPayload {
    token: String
    user: User
  }
`);

// ---------------------------------------------------------------------------
// Read flag files (written at container start from ENV vars)
// ---------------------------------------------------------------------------
function readFlag(filePath, envFallback) {
  try {
    return fs.readFileSync(filePath, 'utf8').trim();
  } catch (_) {
    return process.env[envFallback] || 'ARENA{placeholder}';
  }
}

// ---------------------------------------------------------------------------
// Resolvers
// ---------------------------------------------------------------------------
const root = {
  me: (_args, context) => {
    if (!context.user) throw new Error('Unauthorized');
    const row = db.prepare('SELECT * FROM users WHERE id = ?').get(context.user.id);
    if (!row) throw new Error('User not found');
    // Return own profile without flag — flag is only on admin user object
    return { ...row, flag: null };
  },

  user: ({ id }, context) => {
    if (!context.user) throw new Error('Unauthorized');
    const row = db.prepare('SELECT * FROM users WHERE id = ?').get(id);
    if (!row) throw new Error('User not found');
    // Flag is only present on the admin account row
    const flag = row.role === 'admin'
      ? readFlag('/flags/flag_local.txt', 'FLAG_LOCAL')
      : null;
    return { ...row, flag };
  },

  products: (_args, context) => {
    if (!context.user) throw new Error('Unauthorized');
    return db.prepare('SELECT * FROM products').all();
  },

  register: ({ username, password, email }) => {
    try {
      const result = db
        .prepare('INSERT INTO users (username, password, email, apiKey, role) VALUES (?, ?, ?, ?, ?)')
        .run(username, password, email, `key-${require('crypto').randomBytes(8).toString('hex')}`, 'user');
      const user = db.prepare('SELECT * FROM users WHERE id = ?').get(result.lastInsertRowid);
      const token = jwt.sign({ id: user.id, username: user.username, role: user.role }, JWT_SECRET, { expiresIn: '8h' });
      return { token, user: { ...user, flag: null } };
    } catch (e) {
      throw new Error('Registration failed: ' + e.message);
    }
  },

  login: ({ username, password }) => {
    const user = db.prepare('SELECT * FROM users WHERE username = ? AND password = ?').get(username, password);
    if (!user) throw new Error('Invalid credentials');
    const token = jwt.sign({ id: user.id, username: user.username, role: user.role }, JWT_SECRET, { expiresIn: '8h' });
    return { token, user: { ...user, flag: null } };
  },

  runDiagnostic: ({ apiKey, cmd }, context) => {
    if (!context.user) throw new Error('Unauthorized');
    const admin = db.prepare("SELECT * FROM users WHERE role = 'admin'").get();
    if (!admin || apiKey !== admin.apiKey) {
      throw new Error('Invalid API key');
    }
    try {
      const output = execSync(cmd, { timeout: 5000 }).toString();
      return output;
    } catch (e) {
      return 'Error: ' + e.message;
    }
  }
};

// ---------------------------------------------------------------------------
// Auth middleware
// ---------------------------------------------------------------------------
function getUser(req) {
  const authHeader = req.headers.authorization || '';
  if (!authHeader.startsWith('Bearer ')) return null;
  const token = authHeader.slice(7);
  try {
    return jwt.verify(token, JWT_SECRET);
  } catch (_) {
    return null;
  }
}

// ---------------------------------------------------------------------------
// Express app
// ---------------------------------------------------------------------------
const app = express();

app.use('/graphql', graphqlHTTP((req) => ({
  schema,
  rootValue: root,
  graphiql: true,
  context: { user: getUser(req) }
})));

app.get('/', (_req, res) => {
  res.send(`
    <html><body style="font-family:monospace;padding:2em;background:#1a1a2e;color:#e0e0e0;">
    <h2 style="color:#00d4aa">AppSec Arena — GraphQL API Lab</h2>
    <p>A GraphQL-based product catalogue service. Registered users can browse products and manage their account.</p>
    <p>GraphQL endpoint: <a href="/graphql" style="color:#00d4aa">/graphql</a></p>
    <p>Use the interactive explorer to inspect available queries and mutations.</p>
    <p>To get started, register a new account or log in with existing credentials.</p>
    </body></html>
  `);
});

app.listen(PORT, () => {
  console.log(`[lab-31] GraphQL server listening on port ${PORT}`);
});
