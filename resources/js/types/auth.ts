export type UserRole = "student" | "lecturer" | "org_admin" | "admin";

export type TenantMembership = {
  class_id: number;
  class_name: string;
  academic_year: string;
  school_id: number;
  school_name: string;
  role_in_class: "student" | "teacher";
};

export type TenantContext = {
  organization: {
    id: number;
    name: string;
    plan: string;
  };
  memberships: TenantMembership[];
};

export type AuthPermissions = {
  use_editors: boolean;
};
