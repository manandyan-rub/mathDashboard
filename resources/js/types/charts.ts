export type DailyXpPoint = {
  date: string;
  label: string;
  xp: number;
  claims: number;
};

export type XpSource = {
  source: string;
  label: string;
  xp: number;
  claims: number;
};

export type XpEvent = {
  id: number;
  amount: number;
  source: string;
  label: string;
  earned_at: string | null;
  metadata: Record<string, any>;
};

export type ModePopularity = {
  mode: string;
  label: string;
  xp: number;
  claims: number;
};

export type UserLevelDistribution = {
  level: number;
  label: string;
  users: number;
  total_xp: number;
};

export type CompletedLevelDistribution = {
  level_id: number | null;
  label: string;
  users: number;
  order: number;
};

export type ActivityHeatmap = {
  hours: string[];
  days: string[];
  data: [number, number, number][];
  max: number;
};

export type XpAnalytics = {
  profile: {
    total_xp: number;
    current_rank: string;
    completed_levels: number;
    total_stars: number;
  };
  summary: {
    period_xp: number;
    period_claims: number;
    best_day_xp: number;
  };
  period: {
    start: string;
    end: string;
  };
  daily: DailyXpPoint[];
  sources: XpSource[];
  popular_modes: ModePopularity[];
  user_level_distribution: UserLevelDistribution[];
  completed_level_distribution: CompletedLevelDistribution[];
  activity_heatmap: ActivityHeatmap;
  recent: XpEvent[];
};

export type FunnelStep = {
  label: string;
  value: number;
  widthPercent: number;
};

export type InsightCard = {
  title: string;
  value: string;
  description: string;
};
