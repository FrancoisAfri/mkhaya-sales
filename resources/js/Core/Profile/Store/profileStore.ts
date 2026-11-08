// Store/profileStore.ts
import { defineStore } from "pinia";
import {
    type ProfileUser,
    type PositionData,
    type ManagerData,
    type CompanyUnitData,
    type ProfileTab,
    TITLE_LABELS,
} from "../Type/type";

// ── Helper date ───────────────────────────────────────────────────────────────
const formatTimestamp = (
    val: string | number | null | undefined,
    options: Intl.DateTimeFormatOptions,
): string => {
    if (!val) return "—"
    try {
        let date: Date

        if (typeof val === "string") {
            if (/^\d{4}-\d{2}-\d{2}/.test(val)) {
                date = new Date(val)
            } else {
                const asNum = parseInt(val)
                if (isNaN(asNum) || asNum <= 0) return "—"
                date = asNum > 1e10
                    ? new Date(asNum)
                    : new Date(asNum * 1000)
            }
        } else {
            date = val > 1e10
                ? new Date(val)
                : new Date(val * 1000)
        }

        if (isNaN(date.getTime())) return "—"

        const year = date.getFullYear()
        if (year < 1900 || year > 2100) return "—"

        return date.toLocaleDateString("en-US", options)
    } catch {
        return "—"
    }
}
const fullDate:  Intl.DateTimeFormatOptions = { day: "numeric", month: "long", year: "numeric" }
const monthYear: Intl.DateTimeFormatOptions = { month: "long", year: "numeric" }

export const useProfileStore = defineStore("profile", {
    state: () => ({
        user:       null as ProfileUser | null,
        snackbar:   false,
        snackMsg:   "",
        snackColor: "success" as "success" | "error",
        saving:     false,
        activeTab:  "profile" as ProfileTab,
    }),

    getters: {
        userId: (state): string | null => state.user.person?.uuid ?? null,

        profileEditUrl(): string | null {
            const uuid = this.user?.person?.uuid
            return uuid ? `/user/edit/${uuid}` : null
        },

        canEditProfile(): boolean {
            return !!this.user?.uuid
        },

        // ── Name ──
        fullName: (state): string => {
            const p = state.user?.person
            if (!p) return state.user?.name ?? state.user?.email ?? "Unknown"
            const full = `${p.first_name ?? ""} ${p.surname ?? ""}`.trim()
            return full || state.user?.email || "Unknown"
        },

        initials: (state): string => {
            const p = state.user?.person
            if (!p) return (state.user?.email?.[0] ?? "?").toUpperCase()
            const i = `${p.first_name?.[0] ?? ""}${p.surname?.[0] ?? ""}`.toUpperCase()
            return i || (state.user?.email?.[0] ?? "?").toUpperCase()
        },

        // ── Labels ──
        titleLabel: (state): string => {
            const v = state.user?.person?.title
            return v ? TITLE_LABELS[v] ?? "—" : "—"
        },
        roleLabel: (state): string => {
            const roles = state.user?.roles
            if (!roles || roles.length === 0) return "No role"
            return roles[0].name
        },
        avatarUrl: (state): string | null =>
            state.user?.person?.profile_pic_url ?? null,

        // ── Dates — toutes via formatTimestamp ──
        joinedDate: (state): string =>
            formatTimestamp(
                state.user?.person?.date_joined ?? state.user?.created_at,
                monthYear,
            ),

        finishedDate: (state): string =>
            formatTimestamp(state.user?.person?.date_left, fullDate),

        dateLeftFormatted: (state): string =>
            formatTimestamp(state.user?.person?.date_left, fullDate),

        // ── Position ──
        positionName: (state): string =>
            state.user?.person?.hr_position?.name || "—",

        positionId: (state): number | null =>
            state.user?.person?.hr_position?.id ?? null,

        position: (state): PositionData | null =>
            state.user?.person?.hr_position ?? null,

        // ── Company Units ──
        companyUnitsList: (state): string => {
            const units = state.user?.company_units
            if (!units || units.length === 0) return "—"
            return units.map(u => u.name).join(", ")
        },

        companyUnitsFormatted: (state): string => {
            const units = state.user?.company_units
            if (!units || units.length === 0) return "—"
            return units.map(u => `${u.name}${u.type ? ` (${u.type})` : ""}`).join(", ")
        },

        // ── Manager ──
        managerName: (state): string => {
            const m = state.user?.person?.manager
            if (!m) return "—"
            return `${m.first_name ?? ""} ${m.surname ?? ""}`.trim() || "—"
        },

        manager: (state): ManagerData | null =>
            state.user?.person?.manager ?? null,

        // ── User Type / Status ──
        userType: (state): string => {
            const t = state.user?.type
            if (t === 1) return "Employee"
            if (t === 3) return "Admin"
            return "Contact"
        },

        userStatus: (state): string =>
            state.user?.status === 1 ? "Active" : "Inactive",

        isActive: (state): boolean =>
            state.user?.status === 1,

        // ── Division Levels ──
        divisions: (state): CompanyUnitData[] =>
            state.user?.company_units?.filter(u => u.type === "division") ?? [],

        departments: (state): CompanyUnitData[] =>
            state.user?.company_units?.filter(u => u.type === "department") ?? [],
    },

    actions: {
        setUser(data: ProfileUser | null) {
            if (data) {
                if (data.person) {
                    if (data.person.gender === 0) data.person.gender = 1

                    if (data.person.hr_position && typeof data.person.hr_position !== "object")
                        data.person.hr_position = null

                    if (data.person.manager && typeof data.person.manager !== "object")
                        data.person.manager = null
                }

                if (!Array.isArray(data.company_units)) data.company_units = []
                if (!Array.isArray(data.roles))         data.roles = []
            }

            this.user = data
        },

        showSnack(msg: string, color: "success" | "error" = "success") {
            this.snackMsg   = msg
            this.snackColor = color
            this.snackbar   = true
        },

        async refreshUser() {
            try {
                const response = await fetch("/api/user/profile")
                const data     = await response.json()
                this.setUser(data)
            } catch (error) {
                console.error("Failed to refresh user data:", error)
            }
        },
    },
})
