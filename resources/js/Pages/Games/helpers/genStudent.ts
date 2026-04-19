import type { Problem } from "@/types/game";
import { rand, pick, gcd, simplify, simplifyTex, mkp } from "./mathUtils";

export function genStudent(): Problem {
  const type = pick([
    "mul",
    "div",
    "sq",
    "sqrt",
    "cube",
    "power",
    "pct",
    "fraction_mul",
    "fraction_add",
    "fraction_sub",
    "fraction_div",
    "neg_add",
    "neg_sub",
    "lcm",
    "gcd",
    "mixed_expr",
    "mod",
    "abs_val",
    "ratio",
    "area_rect",
    "perimeter_rect",
    "celsius_fahrenheit",
    "factor",
    "average",
    "interest",
    "log_basic",
    "speed_dist",
    "proportion",
    "vat",
    "discount",
    "exponent_rule",
    "scientific_notation",
    "area_circle_num",
    "volume_cuboid",
    "diagonal_2d",
    "prime_check",
    "fibonacci",
    "roman_numerals",
    "binary_decimal",
    "decimal_binary",
    "sum_consecutive",
    "sum_squares",
    "arithmetic_series",
  ]);

  if (type === "sq") {
    const n = rand(4, 25);
    return mkp(`${n}²`, `${n}^2 = ?`, String(n * n));
  }
  if (type === "sqrt") {
    const r = [
      4, 9, 16, 25, 36, 49, 64, 81, 100, 121, 144, 169, 196, 225, 256, 289, 324,
      361, 400,
    ];
    const n2 = pick(r);
    return mkp(`√${n2}`, `\\sqrt{${n2}} = ?`, String(Math.sqrt(n2)));
  }
  if (type === "cube") {
    const n = rand(2, 9);
    return mkp(`${n}³`, `${n}^3 = ?`, String(n * n * n));
  }
  if (type === "div") {
    const b = rand(2, 15),
      ans = rand(2, 15),
      a = ans * b;
    return mkp(`${a}÷${b}`, `${a} \\div ${b} = ?`, String(ans));
  }
  if (type === "power") {
    const b = pick([2, 3, 4, 5, 6, 7, 8, 9, 10]),
      e = rand(2, 5);
    return mkp(`${b}^${e}`, `${b}^{${e}} = ?`, String(Math.pow(b, e)));
  }
  if (type === "pct") {
    const p = pick([5, 10, 15, 20, 25, 30, 40, 50, 60, 75, 80]),
      base = rand(20, 500);
    const ans = Math.round((p / 100) * base * 100) / 100;
    return mkp(
      `${p}% of ${base}`,
      `${p}\\% \\text{ of } ${base} = ?`,
      String(ans),
    );
  }
  if (type === "fraction_mul") {
    const n1 = rand(1, 5),
      d1 = rand(2, 8),
      n2 = rand(1, 5),
      d2 = rand(2, 8);
    const ans = simplify(n1 * n2, d1 * d2);
    const latexAns = simplifyTex(n1 * n2, d1 * d2);
    return mkp(
      `(${n1}/${d1})×(${n2}/${d2})`,
      `\\frac{${n1}}{${d1}} \\times \\frac{${n2}}{${d2}} = ?`,
      ans,
      latexAns,
    );
  }
  if (type === "fraction_add") {
    const d = pick([2, 3, 4, 5, 6, 8, 10]),
      n1 = rand(1, d - 1),
      n2 = rand(1, d - 1);
    const ans = simplify(n1 + n2, d);
    const latexAns = simplifyTex(n1 + n2, d);
    return mkp(
      `${n1}/${d}+${n2}/${d}`,
      `\\frac{${n1}}{${d}} + \\frac{${n2}}{${d}} = ?`,
      ans,
      latexAns,
    );
  }
  if (type === "fraction_sub") {
    const d = pick([2, 3, 4, 5, 6, 8, 10]),
      n1 = rand(2, d),
      n2 = rand(1, n1 - 1 || 1);
    const ans = simplify(n1 - n2, d);
    const latexAns = simplifyTex(n1 - n2, d);
    return mkp(
      `${n1}/${d}-${n2}/${d}`,
      `\\frac{${n1}}{${d}} - \\frac{${n2}}{${d}} = ?`,
      ans,
      latexAns,
    );
  }
  if (type === "fraction_div") {
    const n1 = rand(1, 5),
      d1 = rand(2, 8),
      n2 = rand(1, 5),
      d2 = rand(2, 8);
    const ans = simplify(n1 * d2, d1 * n2);
    const latexAns = simplifyTex(n1 * d2, d1 * n2);
    return mkp(
      `(${n1}/${d1})÷(${n2}/${d2})`,
      `\\frac{${n1}}{${d1}} \\div \\frac{${n2}}{${d2}} = ?`,
      ans,
      latexAns,
    );
  }
  if (type === "neg_add") {
    const a = rand(1, 20),
      b = rand(1, 20);
    return mkp(`-${a}+${b}`, `-${a} + ${b} = ?`, String(b - a));
  }
  if (type === "neg_sub") {
    const a = rand(1, 15),
      b = rand(1, 15);
    return mkp(`-${a}-${b}`, `-${a} - ${b} = ?`, String(-(a + b)));
  }
  if (type === "lcm") {
    const pairs = [
      [2, 3],
      [3, 4],
      [4, 6],
      [2, 5],
      [3, 5],
      [4, 5],
      [6, 8],
      [5, 6],
      [2, 7],
      [3, 8],
      [4, 9],
      [6, 10],
    ];
    const [a, b] = pick(pairs);
    const lcm = (a * b) / gcd(a, b);
    return mkp(`LCM(${a},${b})`, `\\text{LCM}(${a},${b}) = ?`, String(lcm));
  }
  if (type === "gcd") {
    const pairs = [
      [12, 8],
      [15, 10],
      [18, 12],
      [24, 16],
      [20, 15],
      [36, 24],
      [48, 32],
      [30, 18],
      [42, 28],
      [45, 30],
    ];
    const [a, b] = pick(pairs);
    return mkp(`GCD(${a},${b})`, `\\gcd(${a},${b}) = ?`, String(gcd(a, b)));
  }
  if (type === "mixed_expr") {
    const a = rand(2, 12),
      b = rand(2, 12),
      c = rand(1, 9);
    return mkp(
      `${a}×${b}-${c}`,
      `${a} \\times ${b} - ${c} = ?`,
      String(a * b - c),
    );
  }
  if (type === "mod") {
    const a = rand(10, 50),
      b = rand(2, 9);
    return mkp(`${a} mod ${b}`, `${a} \\bmod ${b} = ?`, String(a % b));
  }
  if (type === "abs_val") {
    const n = rand(-30, -1);
    return mkp(`|${n}|`, `\\left|${n}\\right| = ?`, String(Math.abs(n)));
  }
  if (type === "ratio") {
    const a = rand(2, 6),
      b = rand(2, 6),
      total = pick([10, 20, 30, 40, 50, 60, 100]);
    const share = Math.round((a / (a + b)) * total * 100) / 100;
    return mkp(
      `ratio ${a}:${b} of ${total}`,
      `\\text{Ratio } ${a}:${b} \\text{ of } ${total} \\to \\text{first} = ?`,
      String(share),
    );
  }
  if (type === "area_rect") {
    const l = rand(3, 20),
      w = rand(3, 20);
    return mkp(
      `area ${l}×${w}`,
      `A = l \\times w,\\ l=${l},\\ w=${w}`,
      String(l * w),
    );
  }
  if (type === "perimeter_rect") {
    const l = rand(3, 20),
      w = rand(3, 20);
    return mkp(
      `perim ${l},${w}`,
      `P = 2(l+w),\\ l=${l},\\ w=${w}`,
      String(2 * (l + w)),
    );
  }
  if (type === "celsius_fahrenheit") {
    const c = pick([0, 10, 20, 25, 30, 37, 40, 100, -10, -40]);
    const f = (c * 9) / 5 + 32;
    return mkp(
      `${c}°C→°F`,
      `${c}^{\\circ}C \\to {^\\circ}F = ?`,
      String(f),
      `${f}^{\\circ}F`,
    );
  }
  if (type === "factor") {
    const ns = [
      6, 8, 9, 10, 12, 14, 15, 16, 18, 20, 21, 22, 24, 25, 26, 27, 28, 30, 32,
      33, 35,
    ];
    const n = pick(ns);
    let sf = n;
    for (let i = 2; i < n; i++) {
      if (n % i === 0) {
        sf = i;
        break;
      }
    }
    return mkp(
      `smallest prime of ${n}`,
      `\\text{Smallest prime factor of } ${n} = ?`,
      String(sf),
    );
  }
  if (type === "average") {
    const count = rand(3, 5);
    const nums = Array.from({ length: count }, () => rand(1, 20));
    const avg = Math.round((nums.reduce((a, b) => a + b, 0) / count) * 10) / 10;
    return mkp(
      `avg ${nums.join(",")}`,
      `\\bar{x}\\text{ of }${nums.join(",\\ ")} = ?`,
      String(avg),
    );
  }
  if (type === "interest") {
    const p = pick([100, 200, 500, 1000]),
      r = pick([5, 10, 15, 20]),
      t = rand(1, 5);
    return mkp(
      `SI P=${p},r=${r}%,t=${t}`,
      `SI = \\frac{P \\cdot r \\cdot t}{100},\\ P=${p},\\ r=${r},\\ t=${t}`,
      String(((p * r) / 100) * t),
    );
  }
  if (type === "log_basic") {
    const ch: [string, string, string, string][] = [
      ["log₂(8)", "\\log_2 8 = ?", "3", "3"],
      ["log₂(16)", "\\log_2 16 = ?", "4", "4"],
      ["log₁₀(100)", "\\log_{10} 100 = ?", "2", "2"],
      ["log₃(9)", "\\log_3 9 = ?", "2", "2"],
      ["log₅(25)", "\\log_5 25 = ?", "2", "2"],
      ["log₂(32)", "\\log_2 32 = ?", "5", "5"],
    ];
    const [d, lq, a, la] = pick(ch);
    return mkp(d, lq, a, la);
  }
  if (type === "speed_dist") {
    const s = rand(20, 120),
      t = pick([1, 2, 3, 4, 5]);
    return mkp(
      `d=s×t,s=${s},t=${t}`,
      `d = s \\cdot t,\\ s=${s},\\ t=${t}`,
      String(s * t),
    );
  }
  if (type === "proportion") {
    const a = rand(2, 8),
      b = rand(2, 8),
      c = rand(2, 8);
    const ans = Math.round(((b * c) / a) * 100) / 100;
    return mkp(`${a}:${b}=${c}:?`, `${a}:${b} = ${c}:?`, String(ans));
  }
  if (type === "vat") {
    const price = rand(10, 200),
      vat = pick([5, 10, 15, 20, 25]);
    const total = Math.round(price * (1 + vat / 100) * 100) / 100;
    return mkp(
      `$${price}+${vat}%VAT`,
      `\\$${price} \\times (1 + ${vat}\\%) = ?`,
      String(total),
    );
  }
  if (type === "discount") {
    const price = rand(50, 300),
      disc = pick([10, 20, 25, 30, 50]);
    const final = Math.round(price * (1 - disc / 100) * 100) / 100;
    return mkp(
      `$${price}-${disc}%off`,
      `\\$${price} \\times (1 - ${disc}\\%) = ?`,
      String(final),
    );
  }
  if (type === "exponent_rule") {
    const ch: [string, string, string, string][] = [
      ["x³·x⁴", "x^3 \\cdot x^4 = ?", "x^7", "x^7"],
      ["x⁵/x²", "\\frac{x^5}{x^2} = ?", "x^3", "x^3"],
      ["(x²)³", "(x^2)^3 = ?", "x^6", "x^6"],
      ["x⁰", "x^0 = ?", "1", "1"],
      ["x⁻¹", "x^{-1} = ?", "1/x", "\\frac{1}{x}"],
    ];
    const [d, lq, a, la] = pick(ch);
    return mkp(d, lq, a, la);
  }
  if (type === "scientific_notation") {
    const ch: [string, string, string, string][] = [
      ["3000", "3000 = ? \\times 10^3", "3", "3"],
      ["0.001", "0.001 = ? \\times 10^{-3}", "1", "1"],
      ["45000", "45000 = ? \\times 10^4", "4.5", "4.5"],
      ["2.5×10³", "2.5 \\times 10^3 = ?", "2500", "2500"],
    ];
    const [d, lq, a, la] = pick(ch);
    return mkp(d, lq, a, la);
  }
  if (type === "area_circle_num") {
    const r = rand(1, 8);
    const ans = String(Math.round(3.14 * r * r * 100) / 100);
    return mkp(
      `π·${r}²≈?`,
      `\\pi \\cdot ${r}^2 \\approx ?\\ (\\pi\\approx3.14)`,
      ans,
    );
  }
  if (type === "volume_cuboid") {
    const l = rand(2, 8),
      w = rand(2, 8),
      h = rand(2, 8);
    return mkp(
      `V ${l}×${w}×${h}`,
      `V = l \\cdot w \\cdot h,\\ l=${l},\\ w=${w},\\ h=${h}`,
      String(l * w * h),
    );
  }
  if (type === "diagonal_2d") {
    const t = [
      [3, 4, 5],
      [5, 12, 13],
      [6, 8, 10],
      [8, 15, 17],
    ];
    const [a, b, c] = pick(t);
    return mkp(`diag ${a},${b}`, `d = \\sqrt{${a}^2 + ${b}^2} = ?`, String(c));
  }
  if (type === "prime_check") {
    const primes = [2, 3, 5, 7, 11, 13, 17, 19, 23, 29];
    const composites = [4, 6, 8, 9, 10, 12, 14, 15, 16, 18, 20, 21];
    const isPrime = Math.random() > 0.5;
    const n = isPrime ? pick(primes) : pick(composites);
    return mkp(
      `${n} prime?`,
      `\\text{Is } ${n} \\text{ prime? (1=yes, 0=no)}`,
      isPrime ? "1" : "0",
    );
  }
  if (type === "fibonacci") {
    const fib = [0, 1, 1, 2, 3, 5, 8, 13, 21, 34, 55, 89];
    const i = rand(2, 10);
    return mkp(`F(${i})`, `F(${i}) = ?\\ (F_0=0,\\ F_1=1)`, String(fib[i]));
  }
  if (type === "roman_numerals") {
    const rmap: [number, string][] = [
      [100, "C"],
      [90, "XC"],
      [50, "L"],
      [40, "XL"],
      [10, "X"],
      [9, "IX"],
      [5, "V"],
      [4, "IV"],
      [1, "I"],
    ];
    const vals = [
      1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 14, 15, 19, 20, 40, 50, 90, 100,
    ];
    const n = pick(vals);
    let res = "",
      num = n;
    for (const [v, s] of rmap) {
      while (num >= v) {
        res += s;
        num -= v;
      }
    }
    return mkp(
      `${n}→Roman`,
      `${n} \\text{ in Roman numerals} = ?`,
      res,
      `\\text{${res}}`,
    );
  }
  if (type === "binary_decimal") {
    const n = rand(1, 31);
    return mkp(`${n.toString(2)}₂`, `(${n.toString(2)})_2 = ?_{10}`, String(n));
  }
  if (type === "decimal_binary") {
    const n = rand(1, 15);
    return mkp(
      `${n}→binary`,
      `${n}_{10} = ?_2`,
      n.toString(2),
      `\\text{${n.toString(2)}}`,
    );
  }
  if (type === "sum_consecutive") {
    const n = rand(2, 15);
    return mkp(
      `1+2+…+${n}`,
      `\\sum_{k=1}^{${n}} k = ?`,
      String((n * (n + 1)) / 2),
    );
  }
  if (type === "sum_squares") {
    const n = rand(2, 8);
    return mkp(
      `1²+…+${n}²`,
      `\\sum_{k=1}^{${n}} k^2 = ?`,
      String((n * (n + 1) * (2 * n + 1)) / 6),
    );
  }
  if (type === "arithmetic_series") {
    const a = rand(1, 10),
      d = rand(1, 5),
      n = rand(3, 8);
    const ans = (n / 2) * (2 * a + (n - 1) * d);
    return mkp(
      `ArithSum a=${a},d=${d},n=${n}`,
      `S_n = \\frac{n}{2}(2a+(n-1)d),\\ a=${a},\\ d=${d},\\ n=${n}`,
      String(ans),
    );
  }
  const fa = rand(3, 15),
    fb = rand(3, 15);
  return mkp(`${fa}×${fb}`, `${fa} \\times ${fb} = ?`, String(fa * fb));
}

// ── EINSTEIN problems ─────────────────────────────────────────────────────────
