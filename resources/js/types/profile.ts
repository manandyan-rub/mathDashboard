export type ProfileSection = "profile" | "security" | "preferences";

export type ProfilePreferenceKey =
  | "studyReminders"
  | "weeklySummary"
  | "productUpdates";

export type ProfilePreferenceSetting = {
  key: ProfilePreferenceKey;
  label: string;
  description: string;
  enabled: boolean;
};
