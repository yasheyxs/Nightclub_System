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

interface AuthContextValue {
  user: AuthUser | null;
  token: string | null;
  login: (telefono: string, password: string) => Promise<AuthUser>;
  logout: () => void;
}

export const AuthContext = createContext<AuthContextValue | undefined>(
  undefined
);

interface AuthProviderProps {
  children: ReactNode;
}

const USER_STORAGE_KEY = "santas:user";
const TOKEN_STORAGE_KEY = "santas:token";

const enhanceUser = (rawUser: unknown): AuthUser | null => {
  if (!rawUser || typeof rawUser !== "object") {
    return null;
  }

  const parsed = rawUser as AuthUser;
  const roleSlug = normalizeRoleSlug(
    parsed.rol_slug ?? parsed.rol_nombre ?? null
  );

  return {
    ...parsed,
    rol_slug: roleSlug,
    roleSlug,
  };
};

export function AuthProvider({ children }: AuthProviderProps) {
  const [user, setUser] = useState<AuthUser | null>(() => {
    const stored = localStorage.getItem(USER_STORAGE_KEY);
    if (stored) {
      try {
        return enhanceUser(JSON.parse(stored));
      } catch (error) {
        console.error("Error parsing user data from localStorage", error);
        return null; // Si ocurre un error en el parseo, retornamos null
      }
    }
    return null; // Si no hay datos en localStorage, retornamos null
  });

  const [token, setToken] = useState<string | null>(() => {
    const storedToken = localStorage.getItem(TOKEN_STORAGE_KEY);
    return storedToken || null;
  });

  const login = useCallback(async (telefono: string, password: string) => {
    const response = await fetch(`${API_BASE_URL}/login.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ telefono, password }),
    });

    const data = await response.json();

    if (!response.ok || !data.user || !data.token) {
      throw new Error(data?.error ?? "No se pudo iniciar sesión");
    }

    const normalizedUser = enhanceUser(data.user);

    if (!normalizedUser) {
      throw new Error("No se pudo procesar la información del usuario");
    }

    setUser(normalizedUser);

    setToken(data.token);
    localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(normalizedUser));
    localStorage.setItem(TOKEN_STORAGE_KEY, data.token);
    return normalizedUser;
  }, []);

  const logout = useCallback(() => {
    setUser(null);
    setToken(null);
    localStorage.removeItem(USER_STORAGE_KEY);
    localStorage.removeItem(TOKEN_STORAGE_KEY);
    window.location.href = "/login";
  }, []);

  const value = useMemo(
    () => ({
      user,
      token,
      login,
      logout,
    }),
    [login, logout, token, user]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
