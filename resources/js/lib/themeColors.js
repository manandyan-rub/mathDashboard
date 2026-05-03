export function themeColor(value, fallback = "") {
  const token = normalizeToken(value);
  if (!token) {
    return fallback;
  }

  if (!token.startsWith("--")) {
    return token;
  }

  if (typeof window === "undefined") {
    return fallback || token;
  }

  return (
    getComputedStyle(document.documentElement).getPropertyValue(token).trim() ||
    fallback ||
    token
  );
}

export function themeColorAlpha(value, alpha, fallback = "") {
  const color = themeColor(value, fallback);
  const rgba = colorToRgba(color, alpha);

  return rgba || color;
}

export function themePalette(values) {
  return values.map((value) => themeColor(value));
}

function normalizeToken(value) {
  if (!value) {
    return "";
  }

  const text = String(value).trim();
  const cssVar = text.match(/^var\((--[^)]+)\)$/);

  return cssVar ? cssVar[1] : text;
}

function colorToRgba(color, alpha) {
  if (color.startsWith("#")) {
    return hexToRgba(color, alpha);
  }

  const rgb = color.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
  if (rgb) {
    return `rgba(${rgb[1]}, ${rgb[2]}, ${rgb[3]}, ${alpha})`;
  }

  return "";
}

function hexToRgba(hex, alpha) {
  const value = hex.replace("#", "");
  const normalized =
    value.length === 3
      ? value
          .split("")
          .map((part) => part + part)
          .join("")
      : value;

  if (normalized.length !== 6) {
    return "";
  }

  const red = parseInt(normalized.slice(0, 2), 16);
  const green = parseInt(normalized.slice(2, 4), 16);
  const blue = parseInt(normalized.slice(4, 6), 16);

  return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
}
