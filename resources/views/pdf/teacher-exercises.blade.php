<!DOCTYPE html>
<html lang="{{ $language ?? app()->getLocale() }}">
  <head>
    <meta charset="utf-8" />
    <style>
      @page {
        margin: 28px 30px;
      }
      body {
        font-family: "DejaVu Sans", sans-serif;
        font-size: 12px;
        line-height: 1.5;
        color: #0f172a;
      }
      h1 {
        margin: 0 0 14px 0;
        font-size: 22px;
      }
      h2 {
        margin: 12px 0 8px 0;
        font-size: 18px;
      }
      h3 {
        margin: 10px 0 6px 0;
        font-size: 15px;
      }
      p {
        margin: 6px 0;
        white-space: pre-wrap;
      }
      ol, ul {
        margin: 6px 0 10px 24px;
      }
      img {
        max-width: 100%;
        height: auto;
      }
      figure {
        margin: 10px auto;
        page-break-inside: avoid;
      }
      figcaption {
        margin-top: 5px;
        color: #64748b;
        font-size: 10px;
        text-align: center;
      }
      blockquote {
        margin: 10px 0;
        border-left: 4px solid #4f46e5;
        background: #eef2ff;
        padding: 7px 10px;
      }
      hr {
        border: 0;
        border-top: 1px solid #cbd5e1;
        margin: 12px 0;
      }
      .math-inline {
        display: inline-block;
        font-family: "DejaVu Sans", sans-serif;
        font-style: italic;
        color: #0f172a;
        vertical-align: middle;
        white-space: nowrap;
      }
      .math-display {
        display: block;
        margin: 10px 0;
        padding: 9px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background: #f8fafc;
        font-family: "DejaVu Sans", sans-serif;
        font-size: 14px;
        font-style: italic;
        text-align: center;
      }
      .math-frac {
        display: inline-block;
        min-width: 14px;
        text-align: center;
        vertical-align: middle;
      }
      .math-num {
        display: block;
        border-bottom: 1px solid #0f172a;
        line-height: 1.05;
        padding: 0 2px 1px;
      }
      .math-den {
        display: block;
        line-height: 1.05;
        padding: 1px 2px 0;
      }
      .math-root {
        display: inline-block;
        white-space: nowrap;
      }
      .math-root-symbol {
        font-size: 1.18em;
        vertical-align: middle;
      }
      .math-root-body {
        border-top: 1px solid #0f172a;
        padding: 1px 2px 0;
        vertical-align: middle;
      }
      sup, sub {
        font-size: 72%;
        line-height: 0;
      }
      .pdf-freehand-drawing {
        margin: 10px 0;
        padding: 7px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background: #f8fafc;
        page-break-inside: avoid;
        text-align: center;
      }
      .pdf-freehand-drawing svg,
      .doc svg {
        max-width: 100%;
        height: auto;
      }
      .pdf-freehand-drawing--empty {
        color: #64748b;
        font-size: 11px;
      }
      .doc {
        width: 100%;
      }
    </style>
  </head>
  <body>
    <h1>{{ $title }}</h1>
    <div class="doc">{!! $contentHtml !!}</div>
  </body>
</html>
