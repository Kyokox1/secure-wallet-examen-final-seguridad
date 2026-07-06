import axios from "axios";

// RS-10: se usa Authorization: Bearer en localStorage. Justificación y riesgo:
// - Riesgo: XSS podría robar el token si hay una vulnerabilidad de inyección de script.
// - Mitigación: el frontend escapa toda salida (React lo hace por defecto, nunca usamos
//   dangerouslySetInnerHTML), el access token vive solo 15 min, y el refresh token rota
//   con detección de reúso (si se roba y se usa una vez, toda la familia se revoca).
// Alternativa (cookie HttpOnly) evitaría lectura por JS pero requeriría CSRF tokens;
// se documenta como decisión de diseño aceptada para este examen.

const API_URL = import.meta.env.VITE_API_URL || "http://localhost:8000/api/v1";

const client = axios.create({ baseURL: API_URL });

function getAccessToken() {
  return sessionStorage.getItem("access_token");
}
function getRefreshToken() {
  return sessionStorage.getItem("refresh_token");
}
export function setTokens(access, refresh) {
  sessionStorage.setItem("access_token", access);
  if (refresh) sessionStorage.setItem("refresh_token", refresh);
}
export function clearTokens() {
  sessionStorage.removeItem("access_token");
  sessionStorage.removeItem("refresh_token");
}

client.interceptors.request.use((config) => {
  const token = getAccessToken();
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

let isRefreshing = false;
let queue = [];

client.interceptors.response.use(
  (res) => res,
  async (error) => {
    const original = error.config;

    if (error.response?.status === 401 && !original._retry && getRefreshToken()) {
      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          queue.push({ resolve, reject, original });
        });
      }

      original._retry = true;
      isRefreshing = true;

      try {
        const { data } = await axios.post(`${API_URL}/auth/refresh`, {
          refresh_token: getRefreshToken(),
        });
        setTokens(data.access_token, data.refresh_token);

        queue.forEach(({ resolve, original: o }) => {
          o.headers.Authorization = `Bearer ${data.access_token}`;
          resolve(client(o));
        });
        queue = [];

        original.headers.Authorization = `Bearer ${data.access_token}`;
        return client(original);
      } catch (e) {
        clearTokens();
        window.location.href = "/login";
        return Promise.reject(e);
      } finally {
        isRefreshing = false;
      }
    }

    return Promise.reject(error);
  }
);

export default client;
