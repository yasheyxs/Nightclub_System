import { api } from "@/services/api";

export interface Role {
  id: number;
  nombre: string;
}

export interface User {
  id: number;
  nombre: string;
  telefono: string;
  email: string | null;
  rolId: number;
  rolNombre?: string | null;
  fechaCreacion?: string | null;
  activo: boolean;
}

export interface SaveUserPayload {
  nombre: string;
  telefono: string;
  email?: string | null;
  rolId: number;
  activo: boolean;
  password?: string;
  currentPassword?: string;
  newPassword?: string;
}

const toNumber = (value: unknown): number => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
};

const toString = (value: unknown): string => {
  if (value === null || value === undefined) return "";
  return String(value);
};

const asRecord = (value: unknown): Record<string, unknown> => {
  if (value && typeof value === "object") {
    return value as Record<string, unknown>;
  }
  return {};
};

const normalizeRole = (role: unknown): Role => {
  const source = asRecord(role);
  return {
    id: toNumber(source.id ?? source.Id),
    nombre: toString(source.nombre ?? source.Nombre),
  };
};

const normalizeUser = (user: unknown): User => {
  const source = asRecord(user);
  const nestedRole = asRecord(source.rol ?? source.role);

  return {
    id: toNumber(source.id ?? source.Id),
    nombre: toString(source.nombre ?? source.Nombre),
    telefono: toString(source.telefono ?? source.Telefono),
    email: (source.email ?? source.Email ?? null) as string | null,
    rolId: toNumber(source.rolId ?? source.rol_id ?? source.RolId),
    rolNombre:
      (source.rolNombre ??
        source.rol_nombre ??
        source.RolNombre ??
        nestedRole.nombre) as string | null,
    fechaCreacion: (source.fechaCreacion ??
      source.fecha_creacion ??
      source.FechaCreacion ??
      source.created_at ??
      null) as string | null,
    activo: Boolean(
      source.activo ??
        source.Activo ??
        source.isActive ??
        source.is_active ??
        false
    ),
  };
};

const serializePayload = (payload: SaveUserPayload) => {
  const body: Record<string, unknown> = {
    nombre: payload.nombre,
    telefono: payload.telefono,
    email: payload.email ?? null,
    rolId: payload.rolId,
    rol_id: payload.rolId,
    activo: payload.activo,
  };

  if (payload.password) {
    body.password = payload.password;
  }

  if (payload.currentPassword) {
    body.currentPassword = payload.currentPassword;
  }

  if (payload.newPassword) {
    body.newPassword = payload.newPassword;
  }

  return body;
};

export const listRoles = async (): Promise<Role[]> => {
  const { data } = await api.get("/roles.php");
  if (!Array.isArray(data)) return [];
  return data.map(normalizeRole).filter((role) => role.id !== 0 && role.nombre);
};

export const listUsers = async (): Promise<User[]> => {
  const { data } = await api.get("/usuarios.php");
  if (!Array.isArray(data)) return [];
  return data.map(normalizeUser).filter((user) => user.id !== 0);
};

export const createUser = async (payload: SaveUserPayload): Promise<User> => {
  const body = serializePayload(payload);
  const { data } = await api.post("/usuarios.php", body); //  POST para crear
  return normalizeUser(data);
};

export const updateUser = async (
  id: number,
  payload: SaveUserPayload
): Promise<User> => {
  const body = serializePayload(payload);
  const { data } = await api.put(`/usuarios.php?id=${id}`, body); // pasa id como query
  return normalizeUser(data);
};

export const deleteUser = async (
  id: number,
  options?: { password?: string }
): Promise<void> => {
  const data = options?.password ? { password: options.password } : undefined;
  await api.delete(`/usuarios.php?id=${id}`, { data });
};
