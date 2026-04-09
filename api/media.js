// api/media.js — Proxy de archivos media desde ldm.naitcorp.com
// A diferencia de los rewrites estaticos de Vercel, esta funcion
// puede reenviar el status code real del upstream (206 Partial Content),
// que iOS Safari requiere para reproduccion de audio con Range requests.

var https = require('https');

module.exports = function handler(req, res) {
  var path = req.query.path;
  if (!path) {
    res.statusCode = 400;
    res.end('Missing path');
    return;
  }

  var upstream = 'https://ldm.naitcorp.com/' + path;
  var upstreamHeaders = {};

  // Reenviar Range header — critico para iOS Safari (requiere 206)
  if (req.headers['range']) {
    upstreamHeaders['Range'] = req.headers['range'];
  }

  var proxyReq = https.request(upstream, {
    method: 'GET',
    headers: upstreamHeaders
  }, function(proxyRes) {
    // Usar el status code real del upstream (200 o 206)
    res.statusCode = proxyRes.statusCode;

    var copyHeaders = [
      'content-type', 'content-length', 'content-range',
      'accept-ranges', 'cache-control', 'last-modified', 'etag'
    ];
    copyHeaders.forEach(function(h) {
      if (proxyRes.headers[h]) {
        res.setHeader(h, proxyRes.headers[h]);
      }
    });
    res.setHeader('Access-Control-Allow-Origin', '*');

    proxyRes.pipe(res);
  });

  proxyReq.on('error', function(err) {
    res.statusCode = 502;
    res.end('Proxy error: ' + err.message);
  });

  proxyReq.end();
};
