// Chat.JS — William Moody

var MongoClient = require('mongodb').MongoClient;
var db_url = "mongodb://localhost:27017/";

var session = require('express-session');
var bodyParser = require('body-parser');
var crypto = require('crypto');

var cookieParser = require('cookie-parser');
var serialize = require('node-serialize');

var express = require('express');
var app = new express();
var port = 3000;

var FLAG_LOCAL = process.env.FLAG_LOCAL || 'ARENA{LOCAL_dev}';
var FLAG_PROOF = process.env.FLAG_PROOF || 'ARENA{PROOF_dev}';

app.set('view engine', 'ejs');
app.use('/public', express.static('public'));
app.use(session({
    secret: 'c02b7d24a066adb747fdeb12deb21bfa',
    resave: true,
    saveUninitialized: true
}));
app.use(bodyParser.urlencoded({extended: true}));
app.use(bodyParser.json());
app.use(cookieParser());

/**
 * Home page
 */
app.get('/', function(req, res) {
    console.log('[*] ' + req.ip + ' > GET /');
    MongoClient.connect(db_url, { useNewUrlParser:true, useUnifiedTopology:true }, function(err, db) {
        if (err) throw err;
        var dbo = db.db("chatjs");
        dbo.collection("messages").aggregate([
            { $lookup: { from: 'users', localField: 'author', foreignField: '_id', as: 'author' } },
            { $sort: { datetime: -1 } },
            { $limit: 100 }
        ]).toArray(function(err, result) {
            if (err) throw err;
            var draft = null;
            if (req.session.logged_in && req.cookies.draft) {
                try {
                    draft = serialize.unserialize(Buffer.from(req.cookies.draft, 'base64').toString()).msg;
                } catch(e) {
                    draft = null;
                }
            }
            res.render('pages/index', {messages: result, session: req.session, draft: draft});
            db.close();
        });
    });
});

/**
 * Register page
 */
app.get('/register', function(req, res) {
    console.log('[*] ' + req.ip + ' > GET /register');
    if (req.session.logged_in == true) {
        res.redirect('/');
    } else {
        res.render('pages/register', {session: req.session, error: null});
    }
});

/**
 * Register
 */
app.post('/register', function(req, res) {
    console.log('[*] ' + req.ip + ' > POST /register');
    if (req.session.logged_in == true) {
        res.redirect('/');
    } else {
        var username = req.body.username;
        var password = req.body.password;
        if (username && password) {
            MongoClient.connect(db_url, { useNewUrlParser:true, useUnifiedTopology:true }, function(err, db) {
                if (err) throw err;
                var dbo = db.db("chatjs");
                var query = {$where: `this.username == '${username}'`};
                dbo.collection("users").findOne(query, function(err, result) {
                    if (err) throw err;
                    if (result == null) {
                        res.redirect('/');
                    } else {
                        res.render('pages/register', {session: req.session, error: "User already exists"});
                    }
                    db.close();
                });
            });
        }
    }
});

/**
 * Login
 */
app.post('/auth', function(req, res) {
    console.log('[*] ' + req.ip + ' > POST /auth');
    if (req.session.logged_in == true) {
        res.redirect('/');
    } else {
        var username = req.body.username;
        var password = req.body.password;
        if (username && password) {
            MongoClient.connect(db_url, { useNewUrlParser:true, useUnifiedTopology:true }, function(err, db) {
                if (err) throw err;
                var dbo = db.db("chatjs");
                var sha256_password = (typeof password === 'string')
                    ? crypto.createHash('sha256').update(password).digest('hex')
                    : password;
                dbo.collection("users").findOne({ username: username, password: sha256_password }, function(err, result) {
                    if (err) throw err;
                    if (result) {
                        req.session.logged_in = true;
                        req.session.username = result.username;
                        req.session.user_id = result._id;
                    }
                    req.session.save();
                    db.close();
                });
            });
        }
        res.redirect('/');
    }
});

/**
 * Logout
 */
app.post('/logout', function(req, res) {
    console.log('[*] ' + req.ip + ' > POST /logout');
    if (req.session.logged_in == true) {
        req.session.destroy();
    }
    res.redirect('/');
});

/**
 * Send message or save draft
 */
app.post('/send', function(req, res) {
    console.log('[*] ' + req.ip + ' > POST /send');
    if (req.session.logged_in == true && req.body.message) {
        var post = req.body.post;
        var save = req.body.save;
        if (post != null) {
            res.cookie('draft', '', {expires: new Date()});
            MongoClient.connect(db_url, { useNewUrlParser:true, useUnifiedTopology:true }, function(err, db) {
                if (err) throw err;
                var dbo = db.db("chatjs");
                dbo.collection('messages').insertOne({
                    author: req.session.user_id,
                    datetime: new Date(),
                    text: req.body.message
                }, function() { db.close(); });
            });
        } else if (save != null) {
            var cookie_val = Buffer.from(serialize.serialize({msg: req.body.message})).toString('base64');
            res.cookie('draft', cookie_val, {maxAge: 900000, httpOnly: true});
        }
    }
    res.redirect('/');
});

/**
 * Vault
 */
app.get('/vault', function(req, res) {
    console.log('[*] ' + req.ip + ' > GET /vault');
    if (req.session.logged_in != true) {
        res.redirect('/');
        return;
    }
    res.render('pages/vault', {session: req.session, flag: FLAG_LOCAL});
});

app.listen(port, function() {
    console.log('[+] Chat.JS is running on port ' + port);
});
