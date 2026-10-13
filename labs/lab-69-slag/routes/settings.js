'use strict';

const express = require('express');
const router  = express.Router();

router.use(express.urlencoded({ extended: true }));

router.get('/workspace/:id', (req, res) => {
  res.json({
    workspaceId: req.params.id,
    settings: {
      theme: 'dark',
      notifications: { email: true, inApp: true },
      maxMembers: 50,
    },
  });
});

router.post('/workspace/:id', (req, res) => {
  const b = req.body || {};
  res.json({ status: 'updated', received: b });
});

module.exports = router;
