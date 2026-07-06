import { useState } from "react";
import { v4 as uuidv4 } from "uuid";
import client from "../api/client";
import Navbar from "../components/Navbar";

export default function Transfer() {
  const [form, setForm] = useState({ destinatario: "", monto: "", descripcion: "" });
  const [pending, setPending] = useState(null); // {uuid, destinatario_nombre, requiere_totp}
  const [codigoTotp, setCodigoTotp] = useState("");
  const [error, setError] = useState("");
  const [msg, setMsg] = useState("");
  const [idemKey] = useState(uuidv4()); // RS-05: una sola idempotency key por intento de formulario

  const submit = async (e) => {
    e.preventDefault();
    setError(""); setMsg("");
    try {
      const { data } = await client.post(
        "/transfers",
        { ...form, monto: Number(form.monto) },
        { headers: { "Idempotency-Key": idemKey } }
      );
      setPending(data);
    } catch (err) {
      setError(err.response?.data?.message || "Error al crear la transferencia.");
    }
  };

  const confirmar = async (confirmar) => {
    setError("");
    try {
      await client.post(`/transfers/${pending.uuid}/confirm`, {
        confirmar,
        codigo_totp: codigoTotp || undefined,
      });
      setMsg(confirmar ? "Transferencia completada." : "Transferencia cancelada.");
      setPending(null);
      setForm({ destinatario: "", monto: "", descripcion: "" });
    } catch (err) {
      setError(err.response?.data?.message || "Error al confirmar.");
    }
  };

  return (
    <div className="card">
      <Navbar />
      <h1>Enviar dinero</h1>
      {error && <p className="error">{error}</p>}
      {msg && <p>{msg}</p>}

      {!pending && (
        <form onSubmit={submit}>
          <input placeholder="Correo o teléfono del destinatario" required
            value={form.destinatario} onChange={(e) => setForm({ ...form, destinatario: e.target.value })} />
          <input type="number" min="1" max="5000" step="0.01" placeholder="Monto (1 - 5000 Bs)" required
            value={form.monto} onChange={(e) => setForm({ ...form, monto: e.target.value })} />
          <input placeholder="Descripción (opcional)"
            value={form.descripcion} onChange={(e) => setForm({ ...form, descripcion: e.target.value })} />
          <button>Continuar</button>
        </form>
      )}

      {pending && (
        <div>
          <p>Confirma el envío a: <strong>{pending.destinatario_nombre}</strong></p>
          <p>Monto: Bs. {form.monto}</p>
          {pending.requiere_totp && (
            <input placeholder="Código TOTP (monto > 500 Bs)" maxLength={6}
              value={codigoTotp} onChange={(e) => setCodigoTotp(e.target.value)} />
          )}
          <button onClick={() => confirmar(true)}>Confirmar envío</button>
          <button onClick={() => confirmar(false)}>Cancelar</button>
        </div>
      )}
    </div>
  );
}
