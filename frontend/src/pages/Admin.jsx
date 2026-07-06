import { useEffect, useState } from "react";
import client from "../api/client";
import Navbar from "../components/Navbar";

export default function Admin() {
  const [tab, setTab] = useState("users");
  const [users, setUsers] = useState([]);
  const [logs, setLogs] = useState([]);

  const loadUsers = async () => {
    const { data } = await client.get("/admin/users");
    setUsers(data.data);
  };
  const loadLogs = async () => {
    const { data } = await client.get("/admin/audit-logs");
    setLogs(data.data);
  };

  useEffect(() => {
    if (tab === "users") loadUsers();
    if (tab === "logs") loadLogs();
  }, [tab]);

  const toggleBlock = async (uuid, bloquear) => {
    await client.patch(`/admin/users/${uuid}/block`, { bloquear });
    loadUsers();
  };

  return (
    <div className="card">
      <Navbar />
      <h1>Panel de administrador</h1>
      <nav>
        <button onClick={() => setTab("users")}>Usuarios</button>
        <button onClick={() => setTab("logs")}>Bitácora de auditoría</button>
      </nav>

      {tab === "users" && (
        <table>
          <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Bloqueado</th><th>Acción</th></tr></thead>
          <tbody>
            {users.map((u) => (
              <tr key={u.uuid}>
                <td>{u.nombre_completo}</td><td>{u.email}</td><td>{u.role}</td>
                <td>{u.is_blocked ? "Sí" : "No"}</td>
                <td><button onClick={() => toggleBlock(u.uuid, !u.is_blocked)}>{u.is_blocked ? "Desbloquear" : "Bloquear"}</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {tab === "logs" && (
        <table>
          <thead><tr><th>Fecha</th><th>Usuario</th><th>Evento</th><th>IP</th><th>User-Agent</th></tr></thead>
          <tbody>
            {logs.map((l) => (
              <tr key={l.id}>
                <td>{new Date(l.created_at).toLocaleString()}</td>
                <td>{l.user?.email || "—"}</td>
                <td>{l.evento}</td>
                <td>{l.ip_address}</td>
                <td>{l.user_agent}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}
