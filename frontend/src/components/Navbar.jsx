import { useNavigate } from "react-router-dom";
import client, { clearTokens } from "../api/client";

export default function Navbar() {
  const navigate = useNavigate();

  const logout = async () => {
    try {
      // RF-10: invalida el refresh token en el servidor, no solo en el cliente.
      await client.post("/auth/logout");
    } catch (e) {
      // Aunque el backend falle (ej. token ya expirado), igual limpiamos el cliente.
    } finally {
      clearTokens();
      navigate("/login");
    }
  };

  return (
    <div className="navbar">
      <span className="navbar-brand">SecureWallet</span>
      <button onClick={logout} className="btn-logout">Cerrar sesión</button>
    </div>
  );
}
