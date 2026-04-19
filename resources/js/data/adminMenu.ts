import {
  Box,
  Calculator,
  EqualApproximately,
  FileText,
  LayoutDashboard,
  NotebookPen,
  Pi,
  Sigma,
  Trophy,
} from "lucide-vue-next";

export interface MenuItem {
  title: string;
  route?: string;
  icon?: any;
  children?: MenuItem[];
}

export const adminMenu: MenuItem[] = [
  {
    title: "Dashboard",
    route: "mathematics.index",
    icon: LayoutDashboard,
  },
  {
    title: "Math Levels",
    route: "math-levels.index",
    icon: Sigma,
  },
  {
    title: "Hall of Fame",
    route: "math-levels.rewards",
    icon: Trophy,
  },
  {
    title: "Teacher Exercise Studio",
    route: "teacher.exercises.studio",
    icon: NotebookPen,
  },
  {
    title: "Calculators",
    children: [
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
  {
    title: "Exercises",
    children: [
      {
        title: "PDF Quiz",
        route: "pdf-quiz.upload",
        icon: FileText,
      },
    ],
  },
];
