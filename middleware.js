// middleware.js — Vercel Edge Middleware
// Hace proxy de /media/* hacia ldm.naitcorp.com/*
// preservando Range requests y respuestas 206 (necesario para iOS Safari).
//
// El rewrite estático de vercel.json devuelve HTTP 200 para Range requests
// en lugar de 206 Partial Content — iOS Safari rechaza el audio silenciosamente.
// Este middleware resuelve eso al ser un proxy fetch real.

export var config = {
  matcher: '/media/:path*'
};

export default function middleware(request) {
  var url      = new URL(request.url);
  var pathname = url.pathname;
  var upstream = 'https://ldm.naitcorp.com' + pathname.replace('/media', '');

  // Forwardear headers originales (incluyendo Range) al upstream
  return fetch(upstream, {
    method:  request.method,
    headers: request.headers
  });
}
