import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import client from "../api/client";
import Navbar from "../components/Navbar";

export default function Dashboard() {
  const [me, setMe] = useState(null);
  const [wallet, setWallet] = useState(null);
  const [monto, setMonto] = useState("");
  const [msg, setMsg] = useState("");

  const load = async () => {
    const [meRes, walletRes] = await Promise.all([client.get("/me"), client.get("/wallet")]);
    setMe(meRes.data);
    setWallet(walletRes.data);
  };

  useEffect(() => { load(); }, []);

  const topup = async (e) => {
    e.preventDefault();
    setMsg("");
    try {
      await client.post("/wallet/topup", { monto: Number(monto) });
      setMonto("");
      setMsg("Recarga exitosa.");
      load();
    } catch (err) {
      setMsg(err.response?.data?.message || "Error al recargar.");
    }
  };

  if (!me || !wallet) return <p>Cargando...</p>;

  return (
    <div className="card">
      <Navbar />
      <h1>Hola, {me.nombre_completo}</h1>
      <h2>Saldo actual: Bs. {wallet.saldo}</h2>
      {!me.mfa_enabled && <p><Link to="/mfa-setup">Activar verificación en dos pasos (recomendado)</Link></p>}

      <form onSubmit={topup}>
        <input type="number" min="1" max="10000" step="0.01" placeholder="Monto a recargar" value={monto} onChange={(e) => setMonto(e.target.value)} required />
        <button>Recargar saldo</button>
      </form>
      {msg && <p>{msg}</p>}

      <nav>
        <Link to="/transfer">Enviar dinero</Link> | <Link to="/history">Historial</Link>
        {me.role === "ADMIN" && <> | <Link to="/admin">Panel admin</Link></>}
      </nav>
    </div>
  );
}
