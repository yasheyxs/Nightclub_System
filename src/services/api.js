import axios from "axios";

export const api = axios.create({
  baseURL:
    import.meta.env.VITE_API_BASE_URL ??
    "https://santas-phpback.4jkbnu.easypanel.host",
  headers: { "Content-Type": "application/json" },
});
