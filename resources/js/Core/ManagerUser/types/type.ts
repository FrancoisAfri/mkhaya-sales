export interface UserProperties {
  id: number;
  email: string;
  roles: { id: number; name: string }[];
  status: number;
  person?: {
    first_name: string | null;
    uuid:string
    surname: string | null;
    initial?: string | null;
    date_joined?: string | null;
    date_left?: string | null;
    position: number | null;
    division?: string | null;
    department?: string | null;
    disabled?: boolean | null;
  } | null;
}
