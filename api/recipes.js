// api/recipes.js — Proxy hacia ldm.naitcorp.com/recipes/api.php
// Necesario para evitar CORS en iOS 9 Safari (no respeta Access-Control en algunos contextos).
// Reenvía GET/POST/PUT/DELETE con sus query params y body.

var https = require('https');
var url   = require('url');
var qs    = require('querystring');

var UPSTREAM = 'https://ldm.naitcorp.com/recipes/api.php';

module.exports = function handler(req, res) {
  // CORS — respuesta para preflight y para todas las respuestas
  res.setHeader('Access-Control-Allow-Origin',  '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  if (req.method === 'OPTIONS') {
    res.statusCode = 200;
    res.end();
    return;
  }

  // Construir query string hacia upstream (reenviar ?id=X si viene)
  var query = {};
  if (req.query && req.query.id) { query.id = req.query.id; }
  var queryStr  = qs.stringify(query);
  var targetUrl = UPSTREAM + (queryStr ? '?' + queryStr : '');
  var parsed    = url.parse(targetUrl);

  var options = {
    hostname: parsed.hostname,
    path:     parsed.path,
    method:   req.method,
    headers:  { 'Content-Type': 'application/json' }
  };

  var proxyReq = https.request(options, function(proxyRes) {
    res.statusCode = proxyRes.statusCode;
    res.setHeader('Content-Type', 'application/json');
    proxyRes.pipe(res);
  });

  proxyReq.on('error', function(err) {
    res.statusCode = 502;
    res.end(JSON.stringify({ error: 'Proxy error: ' + err.message }));
  });

  // POST y PUT tienen body JSON — pipear al upstream
  if (req.method === 'POST' || req.method === 'PUT') {
    req.pipe(proxyReq);
  } else {
    proxyReq.end();
  }
};
