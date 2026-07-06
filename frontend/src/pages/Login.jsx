import { useState } from "react";
import { useNavigate, Link } from "react-router-dom";
import client, { setTokens } from "../api/client";

export default function Login() {
  const [form, setForm] = useState({ email: "", password: "" });
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const onSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setLoading(true);
    try {
      const { data } = await client.post("/auth/login", form);
      if (data.mfa_required) {
        navigate("/mfa-verify", { state: { mfa_ticket: data.mfa_ticket } });
        return;
      }
      setTokens(data.access_token, data.refresh_token);
      navigate("/dashboard");
    } catch (err) {
      setError(err.response?.data?.message || "Error al iniciar sesión.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="auth-page">
      <form onSubmit={onSubmit} className="auth-card">
        <h1>SecureWallet — Ingresar</h1>
        {error && <p className="error">{error}</p>}
        <input
          type="email" placeholder="Correo electrónico" required
          value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })}
        />
        <input
          type="password" placeholder="Contraseña" required
          value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })}
        />
        <button disabled={loading}>{loading ? "Ingresando..." : "Ingresar"}</button>
        <Link to="/register">Crear cuenta nueva</Link>
      </form>
    </div>
  );
}
