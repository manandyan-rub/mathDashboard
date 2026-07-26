import {
  Box,
  Calculator,
  EqualApproximately,
  Globe2,
  LayoutPanelTop,
  LayoutDashboard,
  NotebookPen,
  Pi,
  Sigma,
  Square,
  Trophy,
  UserRound,
} from "lucide-vue-next";
import type { UserRole } from "@/types/auth";

export interface MenuItem {
  title: string;
  route?: string;
  icon?: any;
  roles?: UserRole[];
  children?: MenuItem[];
}

export const adminMenu: MenuItem[] = [
  {
    title: "Dashboard",
    route: "dashboard",
    icon: LayoutDashboard,
    children: [
      {
        title: "Local",
        route: "dashboard.local",
        icon: UserRound,
      },
      {
        title: "Global",
        route: "dashboard.global",
        icon: Globe2,
      },
    ],
  },
  {
    title: "Hall of Fame",
    route: "math-levels.rewards",
    icon: Trophy,
  },
  {
    title: "Math Levels",
    route: "math-levels.index",
    icon: Sigma,
  },
  {
    title: "Math Workspace",
    route: "mathematics.workspace",
    icon: LayoutPanelTop,
  },
  {
    title: "Teacher Exercise Studio",
    route: "teacher.exercises.studio",
    icon: NotebookPen,
    roles: ["lecturer", "admin"],
  },
  {
    title: "Calculators",
    children: [
      {
        title: "2D Geometry",
        route: "mathematics.twoD",
        icon: Square,
      },
      {
        title: "3D Geometry",
        route: "mathematics.threeD",
        icon: Box,
      },
      {
        title: "Integral",
        route: "mathematics.integral",
        icon: Calculator,
      },
    ],
  },
  {
    title: "Games",
    children: [
      {
        title: "1 + 1",
        route: "game.oneplusone",
        icon: Box,
      },
      {
        title: "Equilibrium",
        route: "game.equilibrium",
        icon: EqualApproximately,
      },
    ],
  },
  {
    title: "Formulas",
    children: [
      {
        title: "Formula Library",
        route: "formula.index",
        icon: Pi,
      },
    ],
  },
];
