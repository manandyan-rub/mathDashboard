import axios from "axios";

export type GameXpSource = "oneplusone" | "equilibrium";
export type GameXpMode = "child" | "scholastic" | "student" | "einstein";

export async function awardGameXp(
  game: GameXpSource,
  mode: GameXpMode,
  count = 1,
): Promise<void> {
  try {
    await axios.post("/api/games/xp", {
      game,
      mode,
      count,
    });
  } catch {
    // XP sync should never block gameplay.
  }
}

