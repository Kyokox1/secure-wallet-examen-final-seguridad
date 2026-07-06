import { useState } from "react";
import client from "../api/client";
import Navbar from "../components/Navbar";

export default function MfaSetup() {
  const [qr, setQr] = useState(null);
  const [codigo, setCodigo] = useState("");
  const [msg, setMsg] = useState("");

  const enable = async () => {
    const { data } = await client.post("/auth/mfa/enable");
    setQr(data.qr_code_url);
  };

  const confirm = async (e) => {
    e.preventDefault();
    try {
      await client.post("/auth/mfa/confirm", { codigo });
      setMsg("MFA activado correctamente.");
    } catch (err) {
      setMsg(err.response?.data?.message || "Código inválido.");
    }
  };

  return (
    <div className="card">
      <Navbar />
      <h2>Activar verificación en dos pasos</h2>
      {!qr && <button onClick={enable}>Generar código QR</button>}
      {qr && (
        <>
          {/* Se usa un servicio externo solo para renderizar el QR a partir de la URL otpauth:// */}
          <img
            alt="QR MFA"
            src={`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qr)}`}
          />
          <form onSubmit={confirm}>
            <input value={codigo} onChange={(e) => setCodigo(e.target.value)} maxLength={6} placeholder="Código de 6 dígitos" />
            <button>Confirmar y activar</button>
          </form>
        </>
      )}
      {msg && <p>{msg}</p>}
    </div>
  );
}
