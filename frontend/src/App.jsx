import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import Login from "./pages/Login";
import Register from "./pages/Register";
import MfaVerify from "./pages/MfaVerify";
import MfaSetup from "./pages/MfaSetup";
import Dashboard from "./pages/Dashboard";
import Transfer from "./pages/Transfer";
import History from "./pages/History";
import Admin from "./pages/Admin";

function isAuthenticated() {
  return !!sessionStorage.getItem("access_token");
}

function Private({ children }) {
  return isAuthenticated() ? children : <Navigate to="/login" />;
}

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/mfa-verify" element={<MfaVerify />} />
        <Route path="/mfa-setup" element={<Private><MfaSetup /></Private>} />
        <Route path="/dashboard" element={<Private><Dashboard /></Private>} />
        <Route path="/transfer" element={<Private><Transfer /></Private>} />
        <Route path="/history" element={<Private><History /></Private>} />
        <Route path="/admin" element={<Private><Admin /></Private>} />
        <Route path="*" element={<Navigate to="/dashboard" />} />
      </Routes>
    </BrowserRouter>
  );
}
