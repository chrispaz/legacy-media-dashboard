// fetch-library.js — Vercel Serverless Function (Node.js ES5-style)
// Proxy para evitar CORS al llamar a Dreamhost desde el iPad.
// GET /api/fetch-library

var https = require('https');

var LIBRARY_URL = 'https://ldm.naitcorp.com/library.json';

module.exports = function handler(req, res) {
  // Solo permitir GET
  if (req.method !== 'GET') {
    res.status(405).json({ error: 'Method not allowed' });
    return;
  }

  https.get(LIBRARY_URL, function(response) {
    var data = '';

    // Acumular chunks
    response.on('data', function(chunk) {
      data += chunk;
    });

    // Al terminar, parsear y devolver
    response.on('end', function() {
      try {
        var library = JSON.parse(data);

        res.setHeader('Access-Control-Allow-Origin', '*');
        res.setHeader('Content-Type', 'application/json; charset=utf-8');
        res.setHeader('Cache-Control', 'public, max-age=300'); // 5 min de cache

        res.status(200).json(library);
      } catch (e) {
        res.status(502).json({
          error: 'Invalid JSON from library source',
          detail: e.message
        });
      }
    });

  }).on('error', function(err) {
    // Dreamhost no responde o hay error de red
    res.status(502).json({
      error: 'Failed to fetch library from Dreamhost',
      detail: err.message
    });
  });
};
