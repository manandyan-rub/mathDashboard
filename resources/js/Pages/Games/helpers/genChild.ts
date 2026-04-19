import type { Problem } from "@/types/game";
import { rand, pick, mkp } from "./mathUtils";

export function genChild(): Problem {
  const type = pick([
    "add",
    "sub",
    "mul_small",
    "missing_add",
    "missing_sub",
    "double",
    "half",
    "sequence",
    "count_skip",
    "abs_diff",
    "triple",
    "div_small",
    "add3",
    "sub3",
    "odd_even",
    "next_even",
    "next_odd",
    "mul_by_10",
    "place_value",
    "coins",
    "shapes_sides",
    "number_bonds",
    "complements_10",
    "complements_100",
    "mul5",
  ]);

  if (type === "add") {
    const a = rand(1, 20),
      b = rand(1, 20);
    return mkp(`${a}+${b}`, `${a} + ${b} = ?`, String(a + b));
  }

  if (type === "sub") {
    const a = rand(5, 30),
      b = rand(1, a);
    return mkp(`${a}-${b}`, `${a} - ${b} = ?`, String(a - b));
  }

  if (type === "mul_small") {
    const a = rand(2, 6),
      b = rand(2, 6);
    return mkp(`${a}×${b}`, `${a} \\times ${b} = ?`, String(a * b));
  }

  if (type === "missing_add") {
    const b = rand(1, 12),
      ans = rand(b + 2, 25);
    return mkp(`?+${b}=${ans}`, `? + ${b} = ${ans}`, String(ans - b));
  }

  if (type === "missing_sub") {
    const ans = rand(1, 12),
      b = rand(1, 12),
      a = ans + b;
    return mkp(`${a}-?=${ans}`, `${a} - ? = ${ans}`, String(b));
  }

  if (type === "double") {
    const n = rand(1, 15);
    return mkp(
      `կրկնապատիկ ${n}`,
      `\\text{Կրկնապատիկը ${n}-ի} = ?`,
      String(n * 2),
    );
  }

  if (type === "half") {
    const n = pick([2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 22, 24, 30, 40, 50]);
    return mkp(`կեսը ${n}-ի`, `\\text{Կեսը ${n}-ի} = ?`, String(n / 2));
  }

  if (type === "sequence") {
    const s = rand(1, 10),
      st = rand(1, 6);
    const sq = [s, s + st, s + st * 2, s + st * 3];
    return mkp(
      `${sq[0]},${sq[1]},${sq[2]},?`,
      `${sq[0]},\\ ${sq[1]},\\ ${sq[2]},\\ ?`,
      String(sq[3]),
    );
  }

  if (type === "count_skip") {
    const st = pick([2, 3, 5, 10]),
      s = rand(0, 4) * st;
    return mkp(
      `քայլելով ×${st}`,
      `\\text{Քայլելով} \\times${st}:\\  ${s},\\ ${s + st},\\ ${s + st * 2},\\ ?`,
      String(s + st * 3),
    );
  }

  if (type === "abs_diff") {
    const a = rand(1, 20),
      b = rand(1, 20);
    return mkp(
      `|${a}-${b}|`,
      `\\left|${a}-${b}\\right| = ?`,
      String(Math.abs(a - b)),
    );
  }

  if (type === "triple") {
    const n = rand(1, 10);
    return mkp(`եռապատիկ ${n}`, `\\text{Եռապատիկը ${n}-ի} = ?`, String(n * 3));
  }

  if (type === "div_small") {
    const b = rand(2, 5),
      ans = rand(1, 10),
      a = ans * b;
    return mkp(`${a}÷${b}`, `${a} \\div ${b} = ?`, String(ans));
  }

  if (type === "add3") {
    const a = rand(1, 10),
      b = rand(1, 10),
      c = rand(1, 10);
    return mkp(`${a}+${b}+${c}`, `${a} + ${b} + ${c} = ?`, String(a + b + c));
  }

  if (type === "sub3") {
    const c = rand(1, 5),
      b = rand(1, 5),
      a = rand(c + b + 1, 20);
    return mkp(`${a}-${b}-${c}`, `${a} - ${b} - ${c} = ?`, String(a - b - c));
  }

  if (type === "odd_even") {
    const n = rand(1, 50);
    return mkp(
      `${n} կենտ?`,
      `\\text{Արդյոք ${n}-ը կենտ է? (1=այո, 0=ոչ)}`,
      String(n % 2),
    );
  }

  if (type === "next_even") {
    const n = rand(1, 24) * 2 - 1;
    return mkp(
      `հաջորդ զույգը ${n}-ից հետո`,
      `\\text{Հաջորդ զույգը ${n}-ից հետո} = ?`,
      String(n + 1),
    );
  }

  if (type === "next_odd") {
    const n = rand(1, 24) * 2;
    return mkp(
      `հաջորդ կենտը ${n}-ից հետո`,
      `\\text{Հաջորդ կենտը ${n}-ից հետո} = ?`,
      String(n + 1),
    );
  }

  if (type === "mul_by_10") {
    const n = rand(1, 20);
    return mkp(`${n}×10`, `${n} \\times 10 = ?`, String(n * 10));
  }

  if (type === "place_value") {
    const n = rand(10, 99),
      d = pick(["tens", "ones"]);
    const ans = d === "tens" ? Math.floor(n / 10) : n % 10;

    return mkp(
      `${d === "tens" ? "տասնյակ" : "միավոր"} թիվը ${n}-ում`,
      `\\text{${d === "tens" ? "Տասնյակի" : "Միավորի"} թիվը ${n}-ում} = ?`,
      String(ans),
    );
  }

  if (type === "coins") {
    const c = rand(1, 9),
      v = pick([1, 2, 5, 10]);
    return mkp(
      `${c}×${v} դրամ`,
      `${c} \\times ${v}\\text{ դրամ} = ?`,
      String(c * v),
    );
  }

  if (type === "shapes_sides") {
    const shapes: [string, number][] = [
      ["եռանկյուն", 3],
      ["քառակուսի", 4],
      ["հնգանկյուն", 5],
      ["վեցանկյուն", 6],
      ["ութանկյուն", 8],
    ];
    const [s, n] = pick(shapes);
    return mkp(`կողմերը ${s}`, `\\text{${s}-ի կողմերի քանակը} = ?`, String(n));
  }

  if (type === "number_bonds") {
    const total = pick([5, 6, 7, 8, 9, 10]),
      a = rand(0, total);
    return mkp(`${a}+?=${total}`, `${a} + ? = ${total}`, String(total - a));
  }

  if (type === "complements_10") {
    const n = rand(1, 9);
    return mkp(`?+${n}=10`, `? + ${n} = 10`, String(10 - n));
  }

  if (type === "complements_100") {
    const n = pick([10, 20, 25, 30, 40, 50, 60, 70, 75, 80, 90]);
    return mkp(`?+${n}=100`, `? + ${n} = 100`, String(100 - n));
  }

  const n = rand(1, 12);
  return mkp(`${n}×5`, `${n} \\times 5 = ?`, String(n * 5));
}
