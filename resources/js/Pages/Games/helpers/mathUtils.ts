import type { Problem } from "@/types/game";

export function rand(min: number, max: number): number {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

export function pick<T>(arr: T[]): T {
  return arr[Math.floor(Math.random() * arr.length)];
}

export function gcd(a: number, b: number): number {
  return b === 0 ? a : gcd(b, a % b);
}

export function simplify(n: number, d: number): string {
  if (d === 0) return "0";
  const g = gcd(Math.abs(n), Math.abs(d));
  const sn = n / g,
    sd = d / g;
  return sd === 1 ? String(sn) : `${sn}/${sd}`;
}

export function simplifyTex(n: number, d: number): string {
  if (d === 0) return "0";
  const g = gcd(Math.abs(n), Math.abs(d));
  const sn = n / g,
    sd = d / g;
  return sd === 1 ? String(sn) : `\\frac{${sn}}{${sd}}`;
}

export function tex(s: string): string {
  return s
    .replace(/−/g, "-")
    .replace(/×/g, "\\times ")
    .replace(/÷/g, "\\div ")
    .replace(/√(\d+)/g, "\\sqrt{$1}")
    .replace(/√\(([^)]+)\)/g, "\\sqrt{$1}")
    .replace(/π/g, "\\pi ")
    .replace(/∫/g, "\\int ")
    .replace(/∞/g, "\\infty ")
    .replace(/→/g, "\\to ")
    .replace(/≈/g, "\\approx ")
    .replace(/°/g, "^{\\circ}")
    .replace(/eˣ/g, "e^x")
    .replace(/eᵃᵗ/g, "e^{at}")
    .replace(/(\d+)²/g, "$1^2")
    .replace(/(\d+)³/g, "$1^3")
    .replace(/\b([a-zA-Z])²/g, "$1^2")
    .replace(/\b([a-zA-Z])³/g, "$1^3")
    .replace(/\^(\d+)/g, "^{$1}")
    .replace(/log₂/g, "\\log_2 ")
    .replace(/log₃/g, "\\log_3 ")
    .replace(/log₄/g, "\\log_4 ")
    .replace(/log₅/g, "\\log_5 ")
    .replace(/log₁₀/g, "\\log_{10} ")
    .replace(/\bln\b/g, "\\ln ")
    .replace(/\bsin\b/g, "\\sin ")
    .replace(/\bcos\b/g, "\\cos ")
    .replace(/\btan\b/g, "\\tan ")
    .replace(/\bsec\b/g, "\\sec ")
    .replace(/\bcsc\b/g, "\\csc ")
    .replace(/\bcot\b/g, "\\cot ")
    .replace(/\barcsin\b/g, "\\arcsin ")
    .replace(/\barccos\b/g, "\\arccos ")
    .replace(/\barctan\b/g, "\\arctan ")
    .replace(/d\/dx/g, "\\frac{d}{dx}")
    .replace(/∫₀\^\{?(\w+)\}?/g, "\\int_0^{$1}")
    .replace(/∫₀/g, "\\int_0")
    .replace(/lim x→0/g, "\\lim_{x \\to 0}")
    .replace(/lim x→1/g, "\\lim_{x \\to 1}")
    .replace(/lim x→∞/g, "\\lim_{x \\to \\infty}")
    .replace(/lim x→-∞/g, "\\lim_{x \\to -\\infty}")
    .replace(/lim x→−∞/g, "\\lim_{x \\to -\\infty}")
    .replace(/\((\d+)\/(\d+)\)/g, "\\frac{$1}{$2}")
    .replace(/C\((\d+),(\d+)\)/g, "\\binom{$1}{$2}")
    .replace(/\|([^|]+)\|/g, "\\left|$1\\right|")
    .trim();
}

export function mkp(
  display: string,
  latexQ: string,
  answer: string,
  latexA?: string,
): Problem {
  return { display, latex: latexQ, answer, answerLatex: latexA ?? tex(answer) };
}
