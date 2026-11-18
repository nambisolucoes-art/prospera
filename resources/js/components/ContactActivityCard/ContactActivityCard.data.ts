import { profileActivity } from "@/components/ActivityTimeline/ActivityTimeline.data";
import type { ActivityItem } from "@/components/ActivityTimeline/ActivityTimeline.types";

export const crmActivity: ActivityItem[] = [
  ...profileActivity,
  profileActivity[3],
  profileActivity[4],
];
