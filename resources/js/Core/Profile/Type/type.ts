// types/profile.ts

export interface PositionData {
    id: number;
    name: string;
    description?: string;
    status?: number;
    job_category_id?: number;
}

export interface ManagerData {
    id: number;
    first_name: string;
    surname: string;
    full_name?: string;
    email?: string;
}

export interface CompanyUnitData {
    id: number;
    name: string;
    type: string;
    parent_id?: number | null;
}

export interface RoleData {
    id?: number;
    name: string;
}
interface LeaveProfileData {
    id: number;
    name: string;
}

export interface EmployeeDocumentData {
    id: number;
    doc_description: string | null;
    category_id: number | null;
    division_level_2: number | null;
    division_level_1: number | null;
    hr_person_id: number | null;
    date_from: string | null;
    expirydate: string | null;
    supporting_docs: string | null;
    doc_type_id: number | null;
    qualification_type_id: number | null;
     hr_change_id: number | null;
}

export interface HRPersonData {
    id: number;

    // ── Personal Information ──
    title: number | null;
    uuid: string | null;
    first_name: string | null;
    surname: string | null;
    initial: string | null;
    known_as: string | null;
    id_number: string | null;

    // ── Contact Information ──
    email: string | null;
    cell_number: string | null;
    phone_number: string | null;

    // ── Residential Address ──

    // ── Emergency Contact ──

    // ── Tax Information ──

    // ── Banking Details ──

    // ── Medical Aid ──

    // ── Provident Fund ──

    // ── Work Details ──
    position_id: number | null;
    position: number | null;
    date_joined: string | null;
    date_left: string | null;
    role_id: number | null;
    manager_id: number | null;
    second_manager_id: number | null;
    division_level_1: number | null;
    division_level_2: number | null;
    division_level_3: number | null;
    division_level_4: number | null;
    division_level_5: number | null;

    // ── Package Information ──

    // ── Disability & Employment ──
    disabled: number | null;

    // ── Driving ──
    proof_drive_permit_url: string | null;
    proof_drive_permit_mime: string | null;
    proof_drive_permit_original_name: string | null;

    // ── Profile ──
    profile_pic: string | null;
    profile_pic_url: string | null;
    status: number | null;

    // ── Relations ──
    hr_position?: PositionData | null;
    manager?: ManagerData | null;
    leaveProfile?: LeaveProfileData | null;
    province?: { id: number; name: string } | null;
    country?: { id: number; name: string } | null;
    documents?: EmployeeDocumentData[] | null;
}

export interface ProfileUser {
    id: number;
    uuid: string;
    email: string;
    name: string;
    type: number;
    status: number;
    phone_number: string | null;
    roles: RoleData[];
    person: HRPersonData | null;
    company_units?: CompanyUnitData[] | null;
    companies?: CompanyUnitData[] | null;
    created_at: string;
}

// ── Enums / Constant Types ──
export type ProfileTab =
    | "profile"
    | "work-details"
    | "personal-info"
    | "contact-details"
    | "emergency-contact"
    | "tax-banking"
    | "medical-aid"
    | "provident-fund"
    | "package-info"
    | "driving"
    | "documents";

export type Gender = 1 | 2;
export type Title = 1 | 2 | 3 | 4;
export type Disabled = 1 | 2;
export type EmploymentType = 1 | 2;
export type JobFunction = 1 | 2;
export type CanDrive = 1 | 2;

// ── Labels Maps ──
export const TITLE_LABELS: Record<number, string> = {
    1: "Mr",
    2: "Miss",
    3: "Ms",
    4: "Dr",
};

