import { useState } from "react";
import { useNavigate, Link } from "react-router-dom";
import client from "../api/client";

export default function Register() {
  const [form, setForm] = useState({
    nombre_completo: "", ci: "", email: "", telefono: "", password: "",
  });
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const onChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });

  const onSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setLoading(true);
    try {
      // TODO: reemplazar "demo-captcha-token" por el token real del widget reCAPTCHA/hCaptcha.
      await client.post("/auth/register", { ...form, captcha_token: "demo-captcha-token" });
      navigate("/login", { state: { registered: true } });
    } catch (err) {
      setError(err.response?.data?.message || "Error al registrar.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="auth-page">
      <form onSubmit={onSubmit} className="auth-card">
        <h1>Crear cuenta — SecureWallet</h1>
        {error && <p className="error">{error}</p>}
        <input name="nombre_completo" placeholder="Nombre completo" onChange={onChange} required />
        <input name="ci" placeholder="Carnet de identidad" onChange={onChange} required />
        <input name="email" type="email" placeholder="Correo electrónico" onChange={onChange} required />
        <input name="telefono" placeholder="Teléfono" onChange={onChange} required />
        <input name="password" type="password" placeholder="Contraseña (min 10, Aa1!)" onChange={onChange} required />
        {/* Aquí se debe montar el widget de reCAPTCHA/hCaptcha real (RS-08) */}
        <button disabled={loading}>{loading ? "Creando..." : "Registrarme"}</button>
        <Link to="/login">Ya tengo cuenta</Link>
      </form>
    </div>
  );
}
