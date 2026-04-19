import type { HTMLAttributes } from "vue";
import type { UserModel, WorkspaceModel } from "./resources";

export interface CmsflyConfig {}

export type NavigationMenus = CmsflyConfig extends { menus: string }
  ? CmsflyConfig["menus"]
  : "main";

export interface AuthUser extends UserModel {
  unread_notifications_count: number;
  active_workspace: CmsflyConfig extends { workspaces: true }
    ? WorkspaceModel
    : undefined;
}

export type DefaultPageProps = {
  auth: {
    user: AuthUser | null;
  };
  config: {
    locale: {
      content: string[];
      web: string[];
      default: string;
      current: string;
    };
    app_url: string;
  };
  success: string | null;
};

export interface PaginateTable<T extends object = object> {
  current_page: number;
  data: T[];
  first_page_url: string;
  from: number;
  last_page: number;
  last_page_url: string;
  links: PaginateLink[];
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number;
  total: number;
}

export type SimplePaginateTable<T extends object = object> = Omit<
  PaginateTable<T>,
  "links" | "last_page_url" | "total" | "last_page"
>;

export interface PaginateLink {
  url: string | null;
  label: string;
  active: boolean;
}

export interface FilterResource<
  Table extends object = object,
  Filters extends string = string,
  Sorts extends string = string,
> {
  table: PaginateTable<Table>;
  filters: Record<Filters, string | null>;
  sorts: Record<Sorts, "desc" | "asc" | null>;
}

export interface GroupedPermissions {
  id: number;
  name: string;
  key: string;
  hidden: boolean;
  children: GroupedPermissions[];
}

export interface FormComponentProps {
  class?: HTMLAttributes["class"];
  id?: HTMLAttributes["id"];
  inputClass?: HTMLAttributes["class"];
  description?: string;
  label?: string;
  error?: string;
}

export interface FormFieldBinding<V> {
  id: string;
  label: string;
  error?: string;
  modelValue: V;
  "onUpdate:modelValue": (value: any) => void;
}

export type RecursiveFormFieldBindings<DataStructure extends object> = {
  [K in keyof DataStructure]: DataStructure[K] extends Array<any>
    ? FormFieldBinding<DataStructure[K]>
    : DataStructure[K] extends object
      ? RecursiveFormFieldBindings<DataStructure[K]>
      : FormFieldBinding<DataStructure[K]>;
};

export interface SortableEvent extends CustomEvent {
  type: "update" | "onadd" | "onstart" | "onremove";
  item: HTMLElement;
  clone: HTMLElement;
  from: HTMLElement;
  to: HTMLElement;
  oldIndex: number;
  newIndex: number;
  oldDraggableIndex: number;
  newDraggableIndex: number;
  originalEvent: DragEvent;
  pullMode?: string;
}