// types.ts

export type Gender = 1 | 2
export type Title = 1 | 2 | 3 | 4
export type Disabled = 1 | 2
export type EmploymentType = 1 | 2
export type JobFunction = 1 | 2
export type CanDrive = 1 | 2

// ── Labels Maps ──
export const TITLE_LABELS: Record<number, string> = {
  1: 'Mr',
  2: 'Miss',
  3: 'Ms',
  4: 'Dr',
}

export const GENDER_LABELS: Record<number, string> = {
  1: 'Male',
  2: 'Female',
}

export const MARITAL_STATUS_LABELS: Record<number, string> = {
  1: 'Single',
  2: 'Married',
  3: 'Divorced',
  4: 'Widowed',
  5: 'Separated',
  6: 'In a relationship',
  7: 'Co-habiting',
}

export const ETHNICITY_LABELS: Record<number, string> = {
  1: 'African',
  2: 'Coloured',
  3: 'Indian/Asian',
  4: 'White',
  5: 'Other',
}

export const EMPLOYMENT_TYPE_LABELS: Record<number, string> = {
  1: 'Permanent',
  2: 'Temporary',
}

export const OCCUPATIONAL_LEVEL_LABELS: Record<number, string> = {
  1: 'Senior Management',
  2: 'Middle Management',
  3: 'Junior Management',
  4: 'Semi Skilled',
  5: 'Unskilled',
  6: 'Professional',
  7: 'Skilled',
}

export const JOB_FUNCTION_LABELS: Record<number, string> = {
  1: 'Core/Operational',
  2: 'Support',
}

export const DISABLED_LABELS: Record<number, string> = {
  1: 'Yes',
  2: 'No',
}

export const CAN_DRIVE_LABELS: Record<number, string> = {
  1: 'Yes',
  2: 'No',
}

export const CITIZENSHIP_LABELS: Record<number, string> = {
  1: 'Citizen',
  2: 'Permanent Resident',
  3: 'Work Permit',
  4: 'Other',
}

// Label map global pour le formatValue
export const LABEL_MAPS: Record<string, Record<number, string>> = {
  title: TITLE_LABELS,
  disabled: DISABLED_LABELS,
}

export interface ApprovalItem {
  id: number
  hr_main_id: number
  first_name: string
  surname: string
  is_approved: boolean | number
  created_at: string
  profile_pic_url?: string | null
  [key: string]: unknown
}

export interface HRPerson {
  id: number
  first_name: string
  surname: string
  date_joined: number | null
  date_left: number | null
  title: number | null
  disabled: number | null
  email: string | null
  cell_number: string | null
  phone_number: string | null
  id_number: string | null
  known_as: string | null
  initial: string | null
  profile_pic: string | null
  profile_pic_url?: string | null
  other: string | null
  leave_profile_data?: { name: string } | null
  province?: { name: string } | null
  country?: { name: string } | null
}
