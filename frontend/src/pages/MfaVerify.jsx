import { useState } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import client, { setTokens } from "../api/client";

export default function MfaVerify() {
  const { state } = useLocation();
  const [codigo, setCodigo] = useState("");
  const [error, setError] = useState("");
  const navigate = useNavigate();

  if (!state?.mfa_ticket) {
    navigate("/login");
    return null;
  }

  const onSubmit = async (e) => {
    e.preventDefault();
    setError("");
    try {
      const { data } = await client.post("/auth/mfa/verify", {
        mfa_ticket: state.mfa_ticket, codigo,
      });
      setTokens(data.access_token, data.refresh_token);
      navigate("/dashboard");
    } catch (err) {
      setError(err.response?.data?.message || "Código inválido.");
    }
  };

  return (
    <div className="auth-page">
      <form onSubmit={onSubmit} className="auth-card">
        <h1>Verificación en dos pasos</h1>
        <p>Ingrese el código de 6 dígitos de su Google Authenticator.</p>
        {error && <p className="error">{error}</p>}
        <input
          value={codigo} onChange={(e) => setCodigo(e.target.value)}
          maxLength={6} placeholder="123456" required
        />
        <button>Verificar</button>
      </form>
    </div>
  );
}
