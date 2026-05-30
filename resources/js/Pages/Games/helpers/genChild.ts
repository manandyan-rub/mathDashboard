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
    const a = rand(1, 20);
    const b = rand(1, 20);
    return mkp(`${a}+${b}`, `${a} + ${b} = ?`, String(a + b));
  }

  if (type === "sub") {
    const a = rand(5, 30);
    const b = rand(1, a);
    return mkp(`${a}-${b}`, `${a} - ${b} = ?`, String(a - b));
  }

  if (type === "mul_small") {
    const a = rand(2, 6);
    const b = rand(2, 6);
    return mkp(`${a}x${b}`, `${a} \\times ${b} = ?`, String(a * b));
  }

  if (type === "missing_add") {
    const b = rand(1, 12);
    const ans = rand(b + 2, 25);
    return mkp(`?+${b}=${ans}`, `? + ${b} = ${ans}`, String(ans - b));
  }

  if (type === "missing_sub") {
    const ans = rand(1, 12);
    const b = rand(1, 12);
    const a = ans + b;
    return mkp(`${a}-?=${ans}`, `${a} - ? = ${ans}`, String(b));
  }

  if (type === "double") {
    const n = rand(1, 15);
    return mkp(`double ${n}`, `\\text{Double of } ${n} = ?`, String(n * 2));
  }

  if (type === "half") {
    const n = pick([2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 22, 24, 30, 40, 50]);
    return mkp(`half of ${n}`, `\\text{Half of } ${n} = ?`, String(n / 2));
  }

  if (type === "sequence") {
    const s = rand(1, 10);
    const st = rand(1, 6);
    const sq = [s, s + st, s + st * 2, s + st * 3];
    return mkp(
      `${sq[0]},${sq[1]},${sq[2]},?`,
      `${sq[0]},\\ ${sq[1]},\\ ${sq[2]},\\ ?`,
      String(sq[3]),
    );
  }

  if (type === "count_skip") {
    const st = pick([2, 3, 5, 10]);
    const s = rand(0, 4) * st;
    return mkp(
      `skip count by ${st}`,
      `\\text{Skip count by } ${st}:\\ ${s},\\ ${s + st},\\ ${s + st * 2},\\ ?`,
      String(s + st * 3),
    );
  }

  if (type === "abs_diff") {
    const a = rand(1, 20);
    const b = rand(1, 20);
    return mkp(
      `|${a}-${b}|`,
      `\\left|${a}-${b}\\right| = ?`,
      String(Math.abs(a - b)),
    );
  }

  if (type === "triple") {
    const n = rand(1, 10);
    return mkp(`triple ${n}`, `\\text{Triple of } ${n} = ?`, String(n * 3));
  }

  if (type === "div_small") {
    const b = rand(2, 5);
    const ans = rand(1, 10);
    const a = ans * b;
    return mkp(`${a}/${b}`, `${a} \\div ${b} = ?`, String(ans));
  }

  if (type === "add3") {
    const a = rand(1, 10);
    const b = rand(1, 10);
    const c = rand(1, 10);
    return mkp(`${a}+${b}+${c}`, `${a} + ${b} + ${c} = ?`, String(a + b + c));
  }

  if (type === "sub3") {
    const c = rand(1, 5);
    const b = rand(1, 5);
    const a = rand(c + b + 1, 20);
    return mkp(`${a}-${b}-${c}`, `${a} - ${b} - ${c} = ?`, String(a - b - c));
  }

  if (type === "odd_even") {
    const n = rand(1, 50);
    return mkp(
      `${n} even?`,
      `\\text{Is } ${n} \\text{ even? }(1=\\text{yes},\\ 0=\\text{no})`,
      String(n % 2 === 0 ? 1 : 0),
    );
  }

  if (type === "next_even") {
    const n = rand(1, 24) * 2 - 1;
    return mkp(
      `next even after ${n}`,
      `\\text{Next even after } ${n} = ?`,
      String(n + 1),
    );
  }

  if (type === "next_odd") {
    const n = rand(1, 24) * 2;
    return mkp(
      `next odd after ${n}`,
      `\\text{Next odd after } ${n} = ?`,
      String(n + 1),
    );
  }

  if (type === "mul_by_10") {
    const n = rand(1, 20);
    return mkp(`${n}x10`, `${n} \\times 10 = ?`, String(n * 10));
  }

  if (type === "place_value") {
    const n = rand(10, 99);
    const d = pick(["tens", "ones"]);
    const ans = d === "tens" ? Math.floor(n / 10) : n % 10;

    return mkp(
      `${d} digit in ${n}`,
      `\\text{${d === "tens" ? "Tens" : "Ones"} digit in } ${n} = ?`,
      String(ans),
    );
  }

  if (type === "coins") {
    const c = rand(1, 9);
    const v = pick([1, 2, 5, 10]);
    return mkp(
      `${c} coins of ${v}`,
      `${c} \\times ${v}\\text{ cents} = ?`,
      String(c * v),
    );
  }

  if (type === "shapes_sides") {
    const shapes: [string, number][] = [
      ["triangle", 3],
      ["square", 4],
      ["pentagon", 5],
      ["hexagon", 6],
      ["octagon", 8],
    ];
    const [shape, sides] = pick(shapes);
    return mkp(
      `${shape} sides`,
      `\\text{Number of sides in a ${shape}} = ?`,
      String(sides),
    );
  }

  if (type === "number_bonds") {
    const total = pick([5, 6, 7, 8, 9, 10]);
    const a = rand(0, total);
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
  return mkp(`${n}x5`, `${n} \\times 5 = ?`, String(n * 5));
}
