import { useState, useEffect } from "react";
import axios from "axios";
import KartuSapi from "./KartuSapi";
import Grafik from "./Grafik";
import Riwayat from "./Riwayat";

function App() {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedSapi, setSelectedSapi] = useState(null);
  const [activeTab, setActiveTab] = useState("dashboard");
  const [lastUpdate, setLastUpdate] = useState("");

  // State untuk menyimpan Jam Digital Real-time
  const [currentTime, setCurrentTime] = useState(new Date().toLocaleTimeString("id-ID"));

  const fetchData = async () => {
    try {
      // Diarahkan ke route API Laravel (routes/api.php -> SensorController@index)
      const res = await axios.get("/api/sensor");
      setData(res.data.data || []);
      setLastUpdate(new Date().toLocaleTimeString("id-ID"));
    } catch (err) {
      console.error("Gagal fetch data:", err);
      setData([]);
    } finally {
      setLoading(false);
    }
  };

  // useEffect 1: Mengambil data sensor berkala dari API setiap 1 menit (60000 ms)
  useEffect(() => {
    fetchData();

    const interval = setInterval(() => {
      fetchData();
    }, 60000);

    return () => clearInterval(interval);
  }, []);

  // useEffect 2: Khusus memperbarui Jam Digital setiap 1 detik agar lancar
  useEffect(() => {
    const clockInterval = setInterval(() => {
      setCurrentTime(new Date().toLocaleTimeString("id-ID"));
    }, 1000);

    return () => clearInterval(clockInterval);
  }, []);

  // useEffect 3: Meminta izin notifikasi browser saat aplikasi pertama kali dimuat
  useEffect(() => {
    if ("Notification" in window) {
      Notification.requestPermission();
    }
  }, []);

  // useEffect 4: Memantau data sapi dan mengirimkan ringkasan pop-up Bahaya & Waspada
  useEffect(() => {
    if ("Notification" in window && Notification.permission === "granted" && data.length > 0) {

      const sapiBahaya = data.filter(
        d => d.status_suhu === "danger" || d.status_jantung === "danger" || d.level_stress === "tinggi"
      );

      const sapiWaspada = data.filter(
        d => (d.status_suhu === "warning" || d.status_jantung === "warning" || d.level_stress === "sedang") &&
             !(d.status_suhu === "danger" || d.status_jantung === "danger" || d.level_stress === "tinggi")
      );

      if (sapiBahaya.length > 0 || sapiWaspada.length > 0) {
        const teksBahaya = sapiBahaya.length > 0
          ? `🔴 BAHAYA (${sapiBahaya.length}): ${sapiBahaya.map(s => s.nama).join(", ")}`
          : "";

        const teksWaspada = sapiWaspada.length > 0
          ? `🟡 WASPADA (${sapiWaspada.length}): ${sapiWaspada.map(s => s.nama).join(", ")}`
          : "";

        const pesanBody = [teksBahaya, teksWaspada].filter(Boolean).join("\n");

        new Notification("⚠️ PEMBARUAN KESEHATAN KANDANG", {
          body: pesanBody,
          icon: "/logo.jpeg",
          tag: `alert-sapi-${sapiBahaya.length}-${sapiWaspada.length}`,
        });
      }
    }
  }, [data]);

  const normal = data ? data.filter(
    d => d.status_suhu === "normal" && d.status_jantung === "normal" && d.level_stress === "rendah"
  ).length : 0;

  const danger = data ? data.filter(
    d => d.status_suhu === "danger" || d.status_jantung === "danger" || d.level_stress === "tinggi"
  ).length : 0;

  const warning = data.length - normal - danger;

  return (
    <div className="app">
      <aside className="sidebar">
        <div className="sidebar-logo">
          <div className="logo-image-container">
            <img src="/logo.jpeg" alt="MooSmartHealthGuard" />
          </div>
          <div className="sidebar-title">
            <h2>MooSmart</h2>
            <p>Health Guard</p>
          </div>
        </div>

        <nav className="sidebar-nav">
          <button
            className={activeTab === "dashboard" ? "nav-item active" : "nav-item"}
            onClick={() => setActiveTab("dashboard")}
          >
            Dashboard
          </button>
          <button
            className={activeTab === "grafik" ? "nav-item active" : "nav-item"}
            onClick={() => setActiveTab("grafik")}
          >
            Grafik
          </button>
          <button
            className={activeTab === "riwayat" ? "nav-item active" : "nav-item"}
            onClick={() => setActiveTab("riwayat")}
          >
            Riwayat
          </button>
        </nav>
        <div className="sidebar-status">
          <div className="status-dot normal"></div>
          <span>Sistem aktif</span>
        </div>
      </aside>
      <main className="main">
        <div className="topbar">
          <div>
            <h1 className="page-title">
              {activeTab === "dashboard" && "Dashboard Monitoring"}
              {activeTab === "grafik" && "Grafik Sensor"}
              {activeTab === "riwayat" && "Riwayat Data"}
            </h1>
            <p className="page-sub">Jam Sistem: {currentTime}</p>
          </div>
          <button className="refresh-btn" onClick={fetchData}>
            Refresh
          </button>
        </div>

        {activeTab === "dashboard" && (
          <>
            <div className="stat-grid">
              <div className="stat-card">
                <div>
                  <div className="stat-num">{data.length}</div>
                  <div className="stat-label">Total Sapi</div>
                </div>
              </div>
              <div className="stat-card">
                <div>
                  <div className="stat-num green-text">{normal}</div>
                  <div className="stat-label">Normal</div>
                </div>
              </div>
              <div className="stat-card">
                <div>
                  <div className="stat-num yellow-text">{warning}</div>
                  <div className="stat-label">Waspada</div>
                </div>
              </div>
              <div className="stat-card">
                <div>
                  <div className="stat-num red-text">{danger}</div>
                  <div className="stat-label">Bahaya</div>
                </div>
              </div>
            </div>

            {danger > 0 && (
              <div className="alert-banner">
                Terdapat {danger} sapi dalam kondisi bahaya. Segera lakukan pemeriksaan.
              </div>
            )}

            {loading ? (
              <div className="loading">Memuat data...</div>
            ) : (
              <div className="kartu-grid">
                {data.map(sapi => (
                  <KartuSapi
                    key={sapi.cow_id}
                    sapi={sapi}
                    onClick={() => {
                      setSelectedSapi(sapi);
                      setActiveTab("grafik");
                    }}
                  />
                ))}
              </div>
            )}
          </>
        )}

        {activeTab === "grafik" && (
          <Grafik data={data} selectedSapi={selectedSapi} setSelectedSapi={setSelectedSapi} />
        )}
        {activeTab === "riwayat" && <Riwayat />}
      </main>
    </div>
  );
}

export default App;
