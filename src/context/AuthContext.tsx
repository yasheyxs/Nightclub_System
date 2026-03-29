import {
  createContext,
  ReactNode,
  useCallback,
  useMemo,
  useState,
} from "react";
import { API_BASE_URL } from "@/lib/constants";
import { normalizeRoleSlug, type RoleSlug } from "@/lib/permissions";

export interface AuthUser {
  id: number;
  telefono: string;
  nombre: string;
  email: string;
  rol_id: number;
  rol_nombre?: string | null;
  rol_slug?: string | null;
  roleSlug?: RoleSlug;
  activo?: boolean;
  fecha_creacion?: string;
}

interface LoginResponse {
  ok?: boolean;
  token?: string;
  user?: AuthUser;
  error?: string;
}

interface AuthContextValue {
  user: AuthUser | null;
  token: string | null;
  login: (telefono: string, password: string) => Promise<AuthUser>;
  logout: () => void;
}

interface AuthProviderProps {
  children: ReactNode;
}

export const AuthContext = createContext<AuthContextValue | undefined>(
  undefined,
);

const USER_STORAGE_KEY = "santas:user";
const TOKEN_STORAGE_KEY = "santas:token";

const isRecord = (value: unknown): value is Record<string, unknown> => {
  return typeof value === "object" && value !== null;
};

const enhanceUser = (rawUser: unknown): AuthUser | null => {
  if (!isRecord(rawUser)) {
    return null;
  }

  const id =
    typeof rawUser.id === "number"
      ? rawUser.id
      : typeof rawUser.id === "string" && rawUser.id.trim() !== ""
        ? Number(rawUser.id)
        : NaN;

  const telefono = typeof rawUser.telefono === "string" ? rawUser.telefono : "";
  const nombre = typeof rawUser.nombre === "string" ? rawUser.nombre : "";
  const email = typeof rawUser.email === "string" ? rawUser.email : "";
  const rolId =
    typeof rawUser.rol_id === "number"
      ? rawUser.rol_id
      : typeof rawUser.rol_id === "string" && rawUser.rol_id.trim() !== ""
        ? Number(rawUser.rol_id)
        : NaN;

  if (
    !Number.isFinite(id) ||
    !Number.isFinite(rolId) ||
    telefono === "" ||
    nombre === "" ||
    email === ""
  ) {
    return null;
  }

  const rolNombre =
    typeof rawUser.rol_nombre === "string" ? rawUser.rol_nombre : null;

  const rawRolSlug =
    typeof rawUser.rol_slug === "string" ? rawUser.rol_slug : null;

  const roleSlug = normalizeRoleSlug(rawRolSlug ?? rolNombre ?? null);

  return {
    id,
    telefono,
    nombre,
    email,
    rol_id: rolId,
    rol_nombre: rolNombre,
    rol_slug: roleSlug,
    roleSlug,
    activo: typeof rawUser.activo === "boolean" ? rawUser.activo : undefined,
    fecha_creacion:
      typeof rawUser.fecha_creacion === "string"
        ? rawUser.fecha_creacion
        : undefined,
  };
};

export function AuthProvider({ children }: AuthProviderProps) {
  const [user, setUser] = useState<AuthUser | null>(() => {
    const stored = localStorage.getItem(USER_STORAGE_KEY);

    if (!stored) {
      return null;
    }

    try {
      return enhanceUser(JSON.parse(stored));
    } catch (error: unknown) {
      console.error("Error parsing user data from localStorage", error);
      return null;
    }
  });

  const [token, setToken] = useState<string | null>(() => {
    return localStorage.getItem(TOKEN_STORAGE_KEY);
  });

  const login = useCallback(
    async (telefono: string, password: string): Promise<AuthUser> => {
      const controller = new AbortController();
      const timeoutId = window.setTimeout(() => controller.abort(), 10000);

      const url = `${API_BASE_URL}/login`;

      try {
        console.log("[LOGIN] URL:", url);
        console.log("[LOGIN] Payload:", { telefono, password: "***" });

        const response = await fetch(url, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ telefono, password }),
          signal: controller.signal,
        });

        const rawText = await response.text();

        console.log("[LOGIN] Status:", response.status);
        console.log("[LOGIN] Response text:", rawText);

        let data: LoginResponse | null = null;

        if (rawText.trim() !== "") {
          try {
            const parsed: unknown = JSON.parse(rawText);

            if (isRecord(parsed)) {
              data = {
                ok: typeof parsed.ok === "boolean" ? parsed.ok : undefined,
                token:
                  typeof parsed.token === "string" ? parsed.token : undefined,
                user: enhanceUser(parsed.user),
                error:
                  typeof parsed.error === "string" ? parsed.error : undefined,
              };
            } else {
              throw new Error("Respuesta JSON inválida");
            }
          } catch {
            throw new Error("El backend no devolvió JSON válido");
          }
        }

        if (!response.ok) {
          throw new Error(data?.error ?? `Error HTTP ${response.status}`);
        }

        if (!data?.user || !data.token) {
          throw new Error("Respuesta de login incompleta");
        }

        setUser(data.user);
        setToken(data.token);

        localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(data.user));
        localStorage.setItem(TOKEN_STORAGE_KEY, data.token);

        return data.user;
      } catch (error: unknown) {
        if (error instanceof DOMException && error.name === "AbortError") {
          throw new Error("El servidor tardó demasiado en responder al login");
        }

        if (error instanceof Error) {
          throw error;
        }

        throw new Error("No se pudo iniciar sesión");
      } finally {
        window.clearTimeout(timeoutId);
      }
    },
    [],
  );

  const logout = useCallback((): void => {
    setUser(null);
    setToken(null);
    localStorage.removeItem(USER_STORAGE_KEY);
    localStorage.removeItem(TOKEN_STORAGE_KEY);
    window.location.href = "/login";
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      token,
      login,
      logout,
    }),
    [user, token, login, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
