import type { Problem } from "@/types/game";
import { rand, pick, gcd, simplify, simplifyTex, mkp } from "./mathUtils";

export function genEinstein(): Problem {
  const type = pick([
    "integral_poly",
    "integral_trig",
    "integral_exp",
    "integral_definite",
    "integral_by_parts",
    "derivative_poly",
    "derivative_trig",
    "derivative_chain",
    "derivative_product",
    "derivative_quotient",
    "limit",
    "limit_infinity",
    "limit_lhopital",
    "area_circle",
    "area_triangle",
    "area_trapezoid",
    "area_sector",
    "area_ellipse",
    "area_rhombus",
    "volume_sphere",
    "volume_cone",
    "volume_cylinder",
    "volume_cube",
    "volume_pyramid",
    "volume_torus",
    "surface_sphere",
    "surface_cylinder",
    "surface_cone",
    "surface_cube",
    "pythagorean",
    "circle_circumference",
    "diagonal_rect",
    "diagonal_cube",
    "arc_length",
    "trig_value",
    "trig_identity",
    "trig_inverse",
    "trig_double_angle",
    "log_value",
    "log_rules",
    "natural_log",
    "binomial",
    "combinatorics",
    "probability_basic",
    "algebra_quad",
    "matrix_det",
    "matrix_trace",
    "vector_dot",
    "vector_magnitude",
    "vector_cross_2d",
    "complex_add",
    "complex_mul",
    "complex_modulus",
    "taylor_series",
    "laplace_basic",
    "series_geometric",
    "eigenvalue_2x2",
    "differential_eq_basic",
    "parametric_deriv",
    "algebra_hard",
  ]);

  // ── Integrals ──
  if (type === "integral_poly") {
    const n = rand(1, 6),
      c = rand(1, 5);
    const g2 = gcd(c, n + 1);
    const cn = c / g2,
      cd = (n + 1) / g2;
    const ans =
      cn === 1 && cd === 1
        ? `x^{${n + 1}}+C`
        : cn === 1
          ? `\\frac{x^{${n + 1}}}{${cd}}+C`
          : `\\frac{${cn}x^{${n + 1}}}{${cd}}+C`;
    const disp = c === 1 ? `x^{${n}}` : `${c}x^{${n}}`;
    return mkp(
      `∫${c === 1 ? "" : c}x^${n}dx`,
      `\\int ${disp}\\,dx = ?`,
      ans,
      ans,
    );
  }
  if (type === "integral_trig") {
    const ch: [string, string, string][] = [
      ["∫sin(x)dx", "\\int \\sin x\\,dx = ?", "-\\cos x+C"],
      ["∫cos(x)dx", "\\int \\cos x\\,dx = ?", "\\sin x+C"],
      ["∫sec²(x)dx", "\\int \\sec^2 x\\,dx = ?", "\\tan x+C"],
      ["∫-sin(x)dx", "\\int -\\sin x\\,dx = ?", "\\cos x+C"],
      ["∫csc²(x)dx", "\\int \\csc^2 x\\,dx = ?", "-\\cot x+C"],
      ["∫sec·tan dx", "\\int \\sec x\\tan x\\,dx = ?", "\\sec x+C"],
      ["∫-csc·cot dx", "\\int -\\csc x\\cot x\\,dx = ?", "\\csc x+C"],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }
  if (type === "integral_exp") {
    const ch: [string, string, string][] = [
      ["∫eˣdx", "\\int e^x\\,dx = ?", "e^x+C"],
      ["∫1/x dx", "\\int \\frac{1}{x}\\,dx = ?", "\\ln|x|+C"],
      ["∫aˣdx", "\\int a^x\\,dx = ?", "\\frac{a^x}{\\ln a}+C"],
      ["∫ln(x)dx", "\\int \\ln x\\,dx = ?", "x\\ln x - x+C"],
      ["∫1/√x dx", "\\int \\frac{1}{\\sqrt{x}}\\,dx = ?", "2\\sqrt{x}+C"],
      ["∫√x dx", "\\int \\sqrt{x}\\,dx = ?", "\\frac{2}{3}x^{3/2}+C"],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }
  if (type === "integral_definite") {
    const n = rand(1, 3),
      b = rand(2, 4);
    const ans = Math.round(Math.pow(b, n + 1) / (n + 1));
    return mkp(
      `∫₀^${b} x^${n}dx`,
      `\\int_0^{${b}} x^{${n}}\\,dx = ?`,
      String(ans),
    );
  }
  if (type === "integral_by_parts") {
    const ch: [string, string, string][] = [
      ["∫x·eˣdx", "\\int x e^x\\,dx = ?", "xe^x - e^x+C"],
      ["∫x·sin(x)dx", "\\int x\\sin x\\,dx = ?", "-x\\cos x+\\sin x+C"],
      ["∫x·cos(x)dx", "\\int x\\cos x\\,dx = ?", "x\\sin x+\\cos x+C"],
      ["∫ln(x)dx", "\\int \\ln x\\,dx = ?", "x\\ln x - x+C"],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }

  // ── Derivatives ──
  if (type === "derivative_poly") {
    const n = rand(2, 7),
      c = rand(1, 6);
    const ans = c === 1 ? `${n}x^{${n - 1}}` : `${c * n}x^{${n - 1}}`;
    const disp = c === 1 ? `x^{${n}}` : `${c}x^{${n}}`;
    return mkp(
      `d/dx ${c === 1 ? "" : c}x^${n}`,
      `\\frac{d}{dx}\\left(${disp}\\right) = ?`,
      ans,
      ans,
    );
  }
  if (type === "derivative_trig") {
    const ch: [string, string, string][] = [
      ["d/dx sin(x)", "\\frac{d}{dx}\\sin x = ?", "\\cos x"],
      ["d/dx cos(x)", "\\frac{d}{dx}\\cos x = ?", "-\\sin x"],
      ["d/dx tan(x)", "\\frac{d}{dx}\\tan x = ?", "\\sec^2 x"],
      ["d/dx eˣ", "\\frac{d}{dx}e^x = ?", "e^x"],
      ["d/dx ln(x)", "\\frac{d}{dx}\\ln x = ?", "\\frac{1}{x}"],
      [
        "d/dx arcsin(x)",
        "\\frac{d}{dx}\\arcsin x = ?",
        "\\frac{1}{\\sqrt{1-x^2}}",
      ],
      ["d/dx arctan(x)", "\\frac{d}{dx}\\arctan x = ?", "\\frac{1}{1+x^2}"],
      ["d/dx sec(x)", "\\frac{d}{dx}\\sec x = ?", "\\sec x\\tan x"],
      ["d/dx cot(x)", "\\frac{d}{dx}\\cot x = ?", "-\\csc^2 x"],
      ["d/dx csc(x)", "\\frac{d}{dx}\\csc x = ?", "-\\csc x\\cot x"],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }
  if (type === "derivative_chain") {
    const n = rand(2, 5),
      m = rand(2, 4);
    const ans = `${n * m}x^{${n * m - 1}}`;
    return mkp(
      `d/dx (x^${m})^${n}`,
      `\\frac{d}{dx}\\left(x^{${m}}\\right)^{${n}} = ?`,
      ans,
      ans,
    );
  }
  if (type === "derivative_product") {
    const ch: [string, string, string][] = [
      ["d/dx x·eˣ", "\\frac{d}{dx}(xe^x) = ?", "e^x+xe^x"],
      ["d/dx x·ln(x)", "\\frac{d}{dx}(x\\ln x) = ?", "\\ln x+1"],
      ["d/dx x·sin(x)", "\\frac{d}{dx}(x\\sin x) = ?", "\\sin x+x\\cos x"],
      ["d/dx x²·eˣ", "\\frac{d}{dx}(x^2 e^x) = ?", "2xe^x+x^2e^x"],
      [
        "d/dx eˣ·sin(x)",
        "\\frac{d}{dx}(e^x\\sin x) = ?",
        "e^x\\sin x+e^x\\cos x",
      ],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }
  if (type === "derivative_quotient") {
    const ch: [string, string, string][] = [
      [
        "d/dx sin/x",
        "\\frac{d}{dx}\\frac{\\sin x}{x} = ?",
        "\\frac{x\\cos x-\\sin x}{x^2}",
      ],
      ["d/dx eˣ/x", "\\frac{d}{dx}\\frac{e^x}{x} = ?", "\\frac{xe^x-e^x}{x^2}"],
      [
        "d/dx x²/(x+1)",
        "\\frac{d}{dx}\\frac{x^2}{x+1} = ?",
        "\\frac{x^2+2x}{(x+1)^2}",
      ],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }

  // ── Limits ──
  if (type === "limit") {
    const ch: [string, string, string][] = [
      ["lim sin(x)/x", "\\lim_{x\\to 0}\\frac{\\sin x}{x} = ?", "1"],
      ["lim (eˣ-1)/x", "\\lim_{x\\to 0}\\frac{e^x-1}{x} = ?", "1"],
      ["lim (x²-1)/(x-1)", "\\lim_{x\\to 1}\\frac{x^2-1}{x-1} = ?", "2"],
      ["lim tan(x)/x", "\\lim_{x\\to 0}\\frac{\\tan x}{x} = ?", "1"],
      ["lim (1-cos)/x", "\\lim_{x\\to 0}\\frac{1-\\cos x}{x} = ?", "0"],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }
  if (type === "limit_infinity") {
    const ch: [string, string, string][] = [
      ["lim 1/x →∞", "\\lim_{x\\to\\infty}\\frac{1}{x} = ?", "0"],
      [
        "lim (1+1/x)^x",
        "\\lim_{x\\to\\infty}\\left(1+\\frac{1}{x}\\right)^x = ?",
        "e",
      ],
      ["lim eˣ →-∞", "\\lim_{x\\to-\\infty}e^x = ?", "0"],
      ["lim lnx/x →∞", "\\lim_{x\\to\\infty}\\frac{\\ln x}{x} = ?", "0"],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }
  if (type === "limit_lhopital") {
    const ch: [string, string, string][] = [
      [
        "L'H sin/x",
        "L'H\\hat{o}pital:\\ \\lim_{x\\to 0}\\frac{\\sin x}{x} = ?",
        "1",
      ],
      [
        "L'H x/eˣ",
        "L'H\\hat{o}pital:\\ \\lim_{x\\to\\infty}\\frac{x}{e^x} = ?",
        "0",
      ],
      [
        "L'H (1-cosx)/x²",
        "L'H\\hat{o}pital:\\ \\lim_{x\\to 0}\\frac{1-\\cos x}{x^2} = ?",
        "\\frac{1}{2}",
      ],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }

  // ── Geometry (areas) ──
  if (type === "area_circle") {
    const r = rand(1, 12);
    return mkp(
      `A⊙ r=${r}`,
      `A = \\pi r^2,\\ r=${r} \\to \\frac{A}{\\pi} = ?`,
      String(r * r),
    );
  }
  if (type === "area_triangle") {
    let b = rand(2, 18),
      h = rand(2, 14);
    if ((b * h) % 2 !== 0) b++;
    return mkp(
      `A△ b=${b},h=${h}`,
      `A = \\frac{1}{2}bh,\\ b=${b},\\ h=${h}`,
      String((b * h) / 2),
    );
  }
  if (type === "area_trapezoid") {
    const a = rand(2, 10),
      b = rand(a + 1, 15),
      h = rand(2, 10);
    return mkp(
      `A▱ a=${a},b=${b},h=${h}`,
      `A = \\frac{(a+b)h}{2},\\ a=${a},\\ b=${b},\\ h=${h}`,
      String(((a + b) * h) / 2),
    );
  }
  if (type === "area_sector") {
    const r = rand(1, 8),
      deg = pick([30, 45, 60, 90, 120, 180]);
    const ans = `\\frac{${deg}}{360}\\pi${r}^2`;
    return mkp(
      `sector r=${r},${deg}°`,
      `A = \\frac{\\theta}{360}\\pi r^2,\\ r=${r},\\ \\theta=${deg}^{\\circ}`,
      ans,
      ans,
    );
  }
  if (type === "area_ellipse") {
    const a = rand(2, 8),
      b = rand(2, 8);
    const ans = `\\pi \\cdot ${a} \\cdot ${b}`;
    return mkp(
      `ellipse a=${a},b=${b}`,
      `A = \\pi ab,\\ a=${a},\\ b=${b}`,
      ans,
      ans,
    );
  }
  if (type === "area_rhombus") {
    const d1 = rand(2, 12),
      d2 = rand(2, 12);
    return mkp(
      `rhombus d₁=${d1},d₂=${d2}`,
      `A = \\frac{d_1 d_2}{2},\\ d_1=${d1},\\ d_2=${d2}`,
      String((d1 * d2) / 2),
    );
  }

  // ── Geometry (volumes) ──
  if (type === "volume_sphere") {
    const r = rand(1, 6);
    const ans = `\\frac{4}{3}\\pi ${r}^3`;
    return mkp(
      `V sphere r=${r}`,
      `V = \\frac{4}{3}\\pi r^3,\\ r=${r}`,
      ans,
      ans,
    );
  }
  if (type === "volume_cone") {
    const r = rand(1, 6),
      h = rand(1, 8);
    const ans = `\\frac{1}{3}\\pi ${r}^2 \\cdot ${h}`;
    return mkp(
      `V cone r=${r},h=${h}`,
      `V = \\frac{1}{3}\\pi r^2 h,\\ r=${r},\\ h=${h}`,
      ans,
      ans,
    );
  }
  if (type === "volume_cylinder") {
    const r = rand(1, 6),
      h = rand(1, 8);
    const ans = `\\pi ${r}^2 \\cdot ${h}`;
    return mkp(
      `V cyl r=${r},h=${h}`,
      `V = \\pi r^2 h,\\ r=${r},\\ h=${h}`,
      ans,
      ans,
    );
  }
  if (type === "volume_cube") {
    const a = rand(2, 9);
    return mkp(`V cube s=${a}`, `V = a^3,\\ a=${a}`, String(a * a * a));
  }
  if (type === "volume_pyramid") {
    const b = rand(2, 8),
      h = rand(2, 8);
    const ans = Math.round(((b * b * h) / 3) * 100) / 100;
    return mkp(
      `V pyramid b=${b},h=${h}`,
      `V = \\frac{1}{3}b^2 h,\\ b=${b},\\ h=${h}`,
      String(ans),
    );
  }
  if (type === "volume_torus") {
    const R = rand(2, 6),
      r = rand(1, R - 1);
    const ans = `2\\pi^2 ${r}^2 \\cdot ${R}`;
    return mkp(
      `V torus R=${R},r=${r}`,
      `V = 2\\pi^2 r^2 R,\\ R=${R},\\ r=${r}`,
      ans,
      ans,
    );
  }

  // ── Surface areas ──
  if (type === "surface_sphere") {
    const r = rand(1, 8);
    const ans = `4\\pi ${r}^2`;
    return mkp(`S sphere r=${r}`, `S = 4\\pi r^2,\\ r=${r}`, ans, ans);
  }
  if (type === "surface_cylinder") {
    const r = rand(1, 6),
      h = rand(1, 8);
    const ans = `2\\pi ${r}(${r}+${h})`;
    return mkp(
      `S cyl r=${r},h=${h}`,
      `S = 2\\pi r(r+h),\\ r=${r},\\ h=${h}`,
      ans,
      ans,
    );
  }
  if (type === "surface_cone") {
    const r = rand(1, 5),
      l = rand(r + 1, 10);
    const ans = `\\pi ${r}(${r}+${l})`;
    return mkp(
      `S cone r=${r},l=${l}`,
      `S = \\pi r(r+l),\\ r=${r},\\ l=${l}`,
      ans,
      ans,
    );
  }
  if (type === "surface_cube") {
    const a = rand(1, 8);
    return mkp(`S cube s=${a}`, `S = 6a^2,\\ a=${a}`, String(6 * a * a));
  }

  // ── Other geometry ──
  if (type === "pythagorean") {
    const triples = [
      [3, 4, 5],
      [5, 12, 13],
      [8, 15, 17],
      [6, 8, 10],
      [9, 12, 15],
      [7, 24, 25],
      [20, 21, 29],
    ];
    const [a, b, c] = pick(triples);
    if (Math.random() > 0.5)
      return mkp(`a=${a},b=${b}→c?`, `a=${a},\\ b=${b},\\ c = ?`, String(c));
    return mkp(`a=${a},c=${c}→b?`, `a=${a},\\ c=${c},\\ b = ?`, String(b));
  }
  if (type === "circle_circumference") {
    const r = rand(1, 10);
    const ans = `2\\pi ${r}`;
    return mkp(`C⊙ r=${r}`, `C = 2\\pi r,\\ r=${r}`, ans, ans);
  }
  if (type === "diagonal_rect") {
    const t = [
      [3, 4, 5],
      [5, 12, 13],
      [6, 8, 10],
      [8, 15, 17],
    ];
    const [a, b, c] = pick(t);
    return mkp(`diag ${a},${b}`, `d = \\sqrt{${a}^2+${b}^2} = ?`, String(c));
  }
  if (type === "diagonal_cube") {
    const a = rand(1, 6);
    const ans = `\\sqrt{3}\\cdot ${a}`;
    return mkp(`space diag s=${a}`, `d = \\sqrt{3}\\,a,\\ a=${a}`, ans, ans);
  }
  if (type === "arc_length") {
    const r = rand(1, 8),
      deg = pick([30, 45, 60, 90, 120, 180, 270]);
    const ans = `\\frac{${deg}}{180}\\pi ${r}`;
    return mkp(
      `arc r=${r},${deg}°`,
      `s = \\frac{\\theta}{180}\\pi r,\\ r=${r},\\ \\theta=${deg}^{\\circ}`,
      ans,
      ans,
    );
  }

  // ── Trigonometry ──
  if (type === "trig_value") {
    const ch: [string, string, string][] = [
      ["sin30°", "\\sin 30^{\\circ} = ?", "\\frac{1}{2}"],
      ["cos60°", "\\cos 60^{\\circ} = ?", "\\frac{1}{2}"],
      ["sin90°", "\\sin 90^{\\circ} = ?", "1"],
      ["cos0°", "\\cos 0^{\\circ} = ?", "1"],
      ["tan45°", "\\tan 45^{\\circ} = ?", "1"],
      ["sin60°", "\\sin 60^{\\circ} = ?", "\\frac{\\sqrt{3}}{2}"],
      ["cos30°", "\\cos 30^{\\circ} = ?", "\\frac{\\sqrt{3}}{2}"],
      ["tan30°", "\\tan 30^{\\circ} = ?", "\\frac{1}{\\sqrt{3}}"],
      ["sin45°", "\\sin 45^{\\circ} = ?", "\\frac{\\sqrt{2}}{2}"],
      ["cos45°", "\\cos 45^{\\circ} = ?", "\\frac{\\sqrt{2}}{2}"],
      ["sin0°", "\\sin 0^{\\circ} = ?", "0"],
      ["cos90°", "\\cos 90^{\\circ} = ?", "0"],
      ["sin180°", "\\sin 180^{\\circ} = ?", "0"],
      ["cos180°", "\\cos 180^{\\circ} = ?", "-1"],
      ["tan60°", "\\tan 60^{\\circ} = ?", "\\sqrt{3}"],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }
  if (type === "trig_identity") {
    const ch: [string, string, string][] = [
      ["sin²+cos²", "\\sin^2 x + \\cos^2 x = ?", "1"],
      ["1+tan²", "1 + \\tan^2 x = ?", "\\sec^2 x"],
      ["1+cot²", "1 + \\cot^2 x = ?", "\\csc^2 x"],
      ["sin(2x)", "\\sin(2x) = ?", "2\\sin x\\cos x"],
      ["cos(2x)", "\\cos(2x) = ?", "\\cos^2 x - \\sin^2 x"],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }
  if (type === "trig_inverse") {
    const ch: [string, string, string][] = [
      ["arcsin(1)", "\\arcsin(1) = ?", "\\frac{\\pi}{2}"],
      ["arcsin(0)", "\\arcsin(0) = ?", "0"],
      ["arccos(0)", "\\arccos(0) = ?", "\\frac{\\pi}{2}"],
      ["arctan(1)", "\\arctan(1) = ?", "\\frac{\\pi}{4}"],
      ["arctan(0)", "\\arctan(0) = ?", "0"],
      ["arccos(1)", "\\arccos(1) = ?", "0"],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }
  if (type === "trig_double_angle") {
    const ch: [string, string, string][] = [
      ["sin(60°)", "\\sin(2\\cdot30^{\\circ}) = ?", "\\frac{\\sqrt{3}}{2}"],
      ["cos(90°)", "\\cos(2\\cdot45^{\\circ}) = ?", "0"],
      ["sin(90°)", "\\sin(2\\cdot45^{\\circ}) = ?", "1"],
      ["cos(120°)", "\\cos(2\\cdot60^{\\circ}) = ?", "-\\frac{1}{2}"],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }

  // ── Logarithms ──
  if (type === "log_value") {
    const ch: [string, string, string][] = [
      ["log₂8", "\\log_2 8 = ?", "3"],
      ["log₂16", "\\log_2 16 = ?", "4"],
      ["log₁₀100", "\\log_{10}100 = ?", "2"],
      ["log₃27", "\\log_3 27 = ?", "3"],
      ["log₅25", "\\log_5 25 = ?", "2"],
      ["log₂32", "\\log_2 32 = ?", "5"],
      ["ln(e)", "\\ln e = ?", "1"],
      ["ln(e²)", "\\ln e^2 = ?", "2"],
      ["ln(1)", "\\ln 1 = ?", "0"],
      ["log₄64", "\\log_4 64 = ?", "3"],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }
  if (type === "log_rules") {
    const ch: [string, string, string][] = [
      ["log(ab)", "\\log(ab) = ?", "\\log a+\\log b"],
      ["log(a/b)", "\\log\\frac{a}{b} = ?", "\\log a-\\log b"],
      ["log(aⁿ)", "\\log a^n = ?", "n\\log a"],
      ["logₓx", "\\log_x x = ?", "1"],
      ["logₓ1", "\\log_x 1 = ?", "0"],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }
  if (type === "natural_log") {
    const ch: [string, string, string][] = [
      ["d/dx ln(x)", "\\frac{d}{dx}\\ln x = ?", "\\frac{1}{x}"],
      ["∫1/x dx", "\\int\\frac{1}{x}dx = ?", "\\ln|x|+C"],
      ["ln(e³)", "\\ln e^3 = ?", "3"],
      ["e^(ln5)", "e^{\\ln 5} = ?", "5"],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }

  // ── Combinatorics / Probability ──
  if (type === "binomial") {
    const n = rand(2, 8),
      k = rand(1, n - 1);
    const fact = (x: number): number => (x <= 1 ? 1 : x * fact(x - 1));
    const ans = fact(n) / (fact(k) * fact(n - k));
    return mkp(`C(${n},${k})`, `\\binom{${n}}{${k}} = ?`, String(ans));
  }
  if (type === "combinatorics") {
    const n = rand(3, 7),
      k = rand(2, n);
    const fact = (x: number): number => (x <= 1 ? 1 : x * fact(x - 1));
    return mkp(
      `P(${n},${k})`,
      `P(${n},${k}) = \\frac{${n}!}{(${n}-${k})!} = ?`,
      String(fact(n) / fact(n - k)),
    );
  }
  if (type === "probability_basic") {
    const n = rand(2, 6),
      total = rand(n + 1, 12);
    const ans = simplify(n, total);
    const la = simplifyTex(n, total);
    return mkp(`P(${n}/${total})`, `P = \\frac{${n}}{${total}} = ?`, ans, la);
  }

  // ── Algebra ──
  if (type === "algebra_quad") {
    const r1 = rand(-6, 8),
      r2 = rand(-6, 8);
    const b = -(r1 + r2),
      c = r1 * r2;
    const bStr = b >= 0 ? `+${b}` : String(b);
    const cStr = c >= 0 ? `+${c}` : String(c);
    return mkp(
      `x²${bStr}x${cStr}=0`,
      `x^2${bStr}x${cStr}=0,\\ \\text{larger root}=?`,
      String(Math.max(r1, r2)),
    );
  }

  // ── Matrices / Vectors ──
  if (type === "matrix_det") {
    const a = rand(1, 6),
      b = rand(1, 6),
      c = rand(1, 6),
      d = rand(1, 6);
    return mkp(
      `det [[${a},${b}],[${c},${d}]]`,
      `\\det\\begin{pmatrix}${a}&${b}\\\\${c}&${d}\\end{pmatrix} = ?`,
      String(a * d - b * c),
    );
  }
  if (type === "matrix_trace") {
    const a = rand(1, 9),
      d = rand(1, 9);
    return mkp(
      `tr [[${a},?,?],[?,${d},?]]`,
      `\\text{tr}\\begin{pmatrix}${a}&\\cdot\\\\\\cdot&${d}\\end{pmatrix} = ?`,
      String(a + d),
    );
  }
  if (type === "vector_dot") {
    const a1 = rand(1, 6),
      a2 = rand(1, 6),
      b1 = rand(1, 6),
      b2 = rand(1, 6);
    return mkp(
      `(${a1},${a2})·(${b1},${b2})`,
      `\\langle${a1},${a2}\\rangle \\cdot \\langle${b1},${b2}\\rangle = ?`,
      String(a1 * b1 + a2 * b2),
    );
  }
  if (type === "vector_magnitude") {
    const a = rand(1, 6),
      b = rand(1, 6);
    const mag = Math.sqrt(a * a + b * b);
    const ans = Number.isInteger(mag) ? String(mag) : `√${a * a + b * b}`;
    const la = Number.isInteger(mag) ? String(mag) : `\\sqrt{${a * a + b * b}}`;
    return mkp(`‖(${a},${b})‖`, `\\|\\langle${a},${b}\\rangle\\| = ?`, ans, la);
  }
  if (type === "vector_cross_2d") {
    const a1 = rand(1, 6),
      a2 = rand(1, 6),
      b1 = rand(1, 6),
      b2 = rand(1, 6);
    return mkp(
      `(${a1},${a2})×(${b1},${b2})`,
      `\\langle${a1},${a2}\\rangle \\times \\langle${b1},${b2}\\rangle = ?`,
      String(a1 * b2 - a2 * b1),
    );
  }

  // ── Complex numbers ──
  if (type === "complex_add") {
    const a = rand(1, 8),
      b = rand(1, 8),
      c = rand(1, 8),
      d = rand(1, 8);
    const ans = `${a + c}+${b + d}i`;
    return mkp(
      `(${a}+${b}i)+(${c}+${d}i)`,
      `(${a}+${b}i)+(${c}+${d}i) = ?`,
      ans,
      ans,
    );
  }
  if (type === "complex_mul") {
    const a = rand(1, 4),
      b = rand(1, 4),
      c = rand(1, 4),
      d = rand(1, 4);
    const re = a * c - b * d,
      im = a * d + b * c;
    const ans = `${re}+${im}i`;
    return mkp(
      `(${a}+${b}i)(${c}+${d}i)`,
      `(${a}+${b}i)(${c}+${d}i) = ?`,
      ans,
      ans,
    );
  }
  if (type === "complex_modulus") {
    const a = rand(1, 6),
      b = rand(1, 6);
    const mag = Math.sqrt(a * a + b * b);
    const ans = Number.isInteger(mag) ? String(mag) : `√${a * a + b * b}`;
    const la = Number.isInteger(mag) ? String(mag) : `\\sqrt{${a * a + b * b}}`;
    return mkp(`|${a}+${b}i|`, `\\left|${a}+${b}i\\right| = ?`, ans, la);
  }

  // ── Series / Advanced ──
  if (type === "taylor_series") {
    const ch: [string, string, string][] = [
      ["eˣ Taylor", "e^x \\approx ? \\text{ (first 3)}", "1+x+\\frac{x^2}{2}"],
      [
        "sin Taylor",
        "\\sin x \\approx ? \\text{ (first 2)}",
        "x-\\frac{x^3}{6}",
      ],
      [
        "cos Taylor",
        "\\cos x \\approx ? \\text{ (first 2)}",
        "1-\\frac{x^2}{2}",
      ],
      [
        "ln(1+x) Taylor",
        "\\ln(1+x) \\approx ? \\text{ (first 2)}",
        "x-\\frac{x^2}{2}",
      ],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }
  if (type === "laplace_basic") {
    const ch: [string, string, string][] = [
      ["L{1}", "\\mathcal{L}\\{1\\} = ?", "\\frac{1}{s}"],
      ["L{eᵃᵗ}", "\\mathcal{L}\\{e^{at}\\} = ?", "\\frac{1}{s-a}"],
      ["L{t}", "\\mathcal{L}\\{t\\} = ?", "\\frac{1}{s^2}"],
      ["L{sin(at)}", "\\mathcal{L}\\{\\sin(at)\\} = ?", "\\frac{a}{s^2+a^2}"],
      ["L{cos(at)}", "\\mathcal{L}\\{\\cos(at)\\} = ?", "\\frac{s}{s^2+a^2}"],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }
  if (type === "series_geometric") {
    const a = rand(1, 5),
      r = pick([2, 3]),
      n = rand(3, 6);
    const sum = Math.round(((a * (1 - Math.pow(r, n))) / (1 - r)) * 100) / 100;
    return mkp(
      `geo a=${a},r=${r},n=${n}`,
      `S_n = \\frac{a(1-r^n)}{1-r},\\ a=${a},\\ r=${r},\\ n=${n}`,
      String(sum),
    );
  }
  if (type === "eigenvalue_2x2") {
    const pairs: [[number, number, number, number], [number, number]][] = [
      [
        [2, 0, 0, 3],
        [2, 3],
      ],
      [
        [1, 0, 0, 4],
        [1, 4],
      ],
      [
        [3, 0, 0, 5],
        [3, 5],
      ],
      [
        [0, 1, 1, 0],
        [-1, 1],
      ],
    ];
    const [mat, eigs] = pick(pairs);
    const [a, b, c, d] = mat;
    const ans = `${eigs[0]},${eigs[1]}`;
    return mkp(
      `λ [[${a},${b}],[${c},${d}]]`,
      `\\text{Eigenvalues of }\\begin{pmatrix}${a}&${b}\\\\${c}&${d}\\end{pmatrix} = ?`,
      ans,
      `${eigs[0]},\\ ${eigs[1]}`,
    );
  }
  if (type === "differential_eq_basic") {
    const ch: [string, string, string][] = [
      ["dy/dx=y", "\\frac{dy}{dx} = y,\\ y = ?", "Ce^x"],
      ["dy/dx=ky", "\\frac{dy}{dx} = ky,\\ y = ?", "Ce^{kx}"],
      ["y''+y=0", "y'' + y = 0,\\ y = ?", "A\\sin x+B\\cos x"],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }
  if (type === "parametric_deriv") {
    const ch: [string, string, string][] = [
      ["x=t²,y=t³", "x=t^2,\\ y=t^3,\\ \\frac{dy}{dx} = ?", "\\frac{3t}{2}"],
      [
        "x=cos,y=sin",
        "x=\\cos t,\\ y=\\sin t,\\ \\frac{dy}{dx} = ?",
        "-\\frac{\\cos t}{\\sin t}",
      ],
    ];
    const [d, lq, la] = pick(ch);
    return mkp(d, lq, la, la);
  }

  const fa = rand(10, 30),
    fb = rand(5, 20),
    fc = rand(2, 9);
  return mkp(
    `(${fa}+${fb})×${fc}`,
    `(${fa}+${fb}) \\times ${fc} = ?`,
    String((fa + fb) * fc),
  );
}
