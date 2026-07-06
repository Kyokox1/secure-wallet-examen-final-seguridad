import { useEffect, useState } from "react";
import client from "../api/client";
import Navbar from "../components/Navbar";

export default function History() {
  const [items, setItems] = useState([]);
  const [tipo, setTipo] = useState("");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState({});

  const load = async (p = 1) => {
    const { data } = await client.get("/transactions", { params: { page: p, tipo: tipo || undefined } });
    setItems(data.data);
    setMeta(data.meta);
    setPage(p);
  };

  useEffect(() => { load(1); }, [tipo]);

  return (
    <div className="card">
      <Navbar />
      <h1>Historial de movimientos</h1>
      <select value={tipo} onChange={(e) => setTipo(e.target.value)}>
        <option value="">Todos</option>
        <option value="RECARGA">Recargas</option>
        <option value="ENVIO">Envíos</option>
        <option value="RECEPCION">Recepciones</option>
      </select>

      <table>
        <thead><tr><th>Fecha</th><th>Tipo</th><th>Monto</th><th>Dirección</th><th>Saldo resultante</th></tr></thead>
        <tbody>
          {items.map((it) => (
            <tr key={it.uuid}>
              <td>{new Date(it.fecha).toLocaleString()}</td>
              <td>{it.tipo}</td>
              <td>Bs. {it.monto}</td>
              <td>{it.direccion}</td>
              <td>Bs. {it.saldo_resultante}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <div>
        <button disabled={page <= 1} onClick={() => load(page - 1)}>Anterior</button>
        <span> Página {meta.current_page} de {meta.last_page} </span>
        <button disabled={page >= meta.last_page} onClick={() => load(page + 1)}>Siguiente</button>
      </div>
    </div>
  );
}
