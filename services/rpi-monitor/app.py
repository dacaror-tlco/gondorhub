import os
import time
import socket
import subprocess
import psutil
from flask import Flask, jsonify, render_template_string

app = Flask(__name__)

# Si montamos el /proc del host dentro del contenedor, le decimos a psutil
# que lea de ahí en lugar del /proc propio del contenedor, para obtener
# datos reales del host (RPi) y no del namespace del contenedor.
HOST_PROC = os.environ.get("HOST_PROC", "/proc")
if os.path.isdir(HOST_PROC):
    psutil.PROCFS_PATH = HOST_PROC

ROOT_PATH = "/host/rootfs" if os.path.isdir("/host/rootfs") else "/"

THERMAL_PATHS = [
    "/host/sys/class/thermal/thermal_zone0/temp",
    "/sys/class/thermal/thermal_zone0/temp",
]


def get_temperature():
    for path in THERMAL_PATHS:
        try:
            with open(path) as f:
                return round(int(f.read().strip()) / 1000, 1)
        except Exception:
            continue
    return None


def get_throttle_status():
    """Best-effort: solo funciona si vcgencmd está disponible en el host/contenedor."""
    try:
        out = subprocess.check_output(
            ["vcgencmd", "get_throttled"], stderr=subprocess.DEVNULL, timeout=1
        ).decode().strip()
        return out
    except Exception:
        return None


def get_disks():
    disks = []
    try:
        partitions = psutil.disk_partitions(all=False)
        for part in partitions:
            try:
                usage = psutil.disk_usage(part.mountpoint)
                disks.append(
                    {
                        "device": part.device,
                        "mountpoint": part.mountpoint,
                        "fstype": part.fstype,
                        "total": usage.total,
                        "used": usage.used,
                        "free": usage.free,
                        "percent": usage.percent,
                    }
                )
            except (PermissionError, FileNotFoundError):
                continue
    except Exception:
        pass
    return disks


@app.route("/api/stats")
def stats():
    cpu_percent = psutil.cpu_percent(interval=0.4)
    cpu_per_core = psutil.cpu_percent(interval=0, percpu=True)
    freq = psutil.cpu_freq()
    mem = psutil.virtual_memory()
    swap = psutil.swap_memory()
    net = psutil.net_io_counters()
    boot_time = psutil.boot_time()
    uptime = time.time() - boot_time

    try:
        load1, load5, load15 = os.getloadavg()
    except Exception:
        load1 = load5 = load15 = None

    try:
        root_usage = psutil.disk_usage(ROOT_PATH)
        root_disk = {
            "total": root_usage.total,
            "used": root_usage.used,
            "free": root_usage.free,
            "percent": root_usage.percent,
        }
    except Exception:
        root_disk = None

    try:
        procs = len(psutil.pids())
    except Exception:
        procs = None

    return jsonify(
        {
            "hostname": socket.gethostname(),
            "cpu": {
                "percent": cpu_percent,
                "per_core": cpu_per_core,
                "count": psutil.cpu_count(),
                "freq_current_mhz": freq.current if freq else None,
                "load_avg": [
                    round(load1, 2) if load1 is not None else None,
                    round(load5, 2) if load5 is not None else None,
                    round(load15, 2) if load15 is not None else None,
                ],
            },
            "memory": {
                "total": mem.total,
                "available": mem.available,
                "used": mem.used,
                "percent": mem.percent,
            },
            "swap": {
                "total": swap.total,
                "used": swap.used,
                "percent": swap.percent,
            },
            "root_disk": root_disk,
            "disks": get_disks(),
            "network": {
                "bytes_sent": net.bytes_sent,
                "bytes_recv": net.bytes_recv,
                "packets_sent": net.packets_sent,
                "packets_recv": net.packets_recv,
            },
            "temperature_c": get_temperature(),
            "throttle_status": get_throttle_status(),
            "processes": procs,
            "uptime_seconds": int(uptime),
            "timestamp": time.time(),
        }
    )


INDEX_HTML = """
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Raspberry Pi Monitor</title>
<style>
  :root {
    --bg: #0f1115;
    --card: #171a21;
    --border: #262b36;
    --text: #e6e9ef;
    --muted: #8b93a7;
    --accent: #5eead4;
    --warn: #fbbf24;
    --danger: #f87171;
    --good: #4ade80;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    background: var(--bg);
    color: var(--text);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    padding: 24px;
  }
  header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    flex-wrap: wrap;
    margin-bottom: 20px;
  }
  h1 { font-size: 1.4rem; margin: 0; }
  .sub { color: var(--muted); font-size: 0.85rem; }
  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 16px;
  }
  .card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px 18px;
  }
  .card h2 {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--muted);
    margin: 0 0 10px 0;
  }
  .metric-value {
    font-size: 1.8rem;
    font-weight: 600;
  }
  .bar-bg {
    background: #262b36;
    border-radius: 6px;
    height: 8px;
    margin-top: 8px;
    overflow: hidden;
  }
  .bar-fill {
    height: 100%;
    border-radius: 6px;
    transition: width 0.4s ease;
    background: var(--accent);
  }
  .cores {
    display: flex;
    gap: 4px;
    margin-top: 10px;
    flex-wrap: wrap;
  }
  .core {
    flex: 1 1 auto;
    min-width: 28px;
    text-align: center;
    font-size: 0.65rem;
    color: var(--muted);
  }
  .core .bar-bg { height: 40px; display: flex; align-items: flex-end; background: #262b36; }
  .core .bar-fill { width: 100%; height: 0%; background: var(--accent); }
  .row { display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--muted); margin-top: 6px;}
  .disk-item { margin-bottom: 12px; }
  .disk-item:last-child { margin-bottom: 0; }
  .disk-item .path { font-size: 0.8rem; color: var(--muted); }
  footer { text-align: center; color: var(--muted); font-size: 0.75rem; margin-top: 24px; }
  .pulse { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--good); margin-right: 6px; animation: pulse 2s infinite; }
  @keyframes pulse { 0% {opacity: 1;} 50% {opacity: 0.3;} 100% {opacity: 1;} }
</style>
</head>
<body>
  <header>
    <div>
      <h1 id="hostname">Raspberry Pi Monitor</h1>
      <div class="sub"><span class="pulse"></span><span id="updated">Cargando...</span></div>
    </div>
    <div class="sub" id="uptime"></div>
  </header>

  <div class="grid">
    <div class="card">
      <h2>CPU</h2>
      <div class="metric-value" id="cpu-percent">--%</div>
      <div class="bar-bg"><div class="bar-fill" id="cpu-bar"></div></div>
      <div class="row"><span id="cpu-count"></span><span id="cpu-freq"></span></div>
      <div class="cores" id="cores"></div>
    </div>

    <div class="card">
      <h2>Memoria RAM</h2>
      <div class="metric-value" id="mem-percent">--%</div>
      <div class="bar-bg"><div class="bar-fill" id="mem-bar"></div></div>
      <div class="row"><span id="mem-used"></span><span id="mem-total"></span></div>
    </div>

    <div class="card">
      <h2>Swap</h2>
      <div class="metric-value" id="swap-percent">--%</div>
      <div class="bar-bg"><div class="bar-fill" id="swap-bar"></div></div>
      <div class="row" id="swap-detail"></div>
    </div>

    <div class="card">
      <h2>Temperatura</h2>
      <div class="metric-value" id="temp">--°C</div>
      <div class="row" id="throttle"></div>
    </div>

    <div class="card">
      <h2>Load Average</h2>
      <div class="metric-value" id="load1">--</div>
      <div class="row" id="load-detail"></div>
    </div>

    <div class="card">
      <h2>Red</h2>
      <div class="row"><span>↑ Enviado</span><span id="net-sent"></span></div>
      <div class="row"><span>↓ Recibido</span><span id="net-recv"></span></div>
      <div class="row"><span>Paquetes ↑/↓</span><span id="net-packets"></span></div>
    </div>

    <div class="card">
      <h2>Procesos</h2>
      <div class="metric-value" id="procs">--</div>
    </div>

    <div class="card" style="grid-column: span 2;">
      <h2>Discos</h2>
      <div id="disks"></div>
    </div>
  </div>

  <footer>Actualiza cada 2 segundos · rpi-monitor</footer>

<script>
function fmtBytes(bytes) {
  if (bytes === null || bytes === undefined) return "-";
  const units = ["B", "KB", "MB", "GB", "TB"];
  let i = 0, val = bytes;
  while (val >= 1024 && i < units.length - 1) { val /= 1024; i++; }
  return val.toFixed(1) + " " + units[i];
}

function fmtUptime(seconds) {
  const d = Math.floor(seconds / 86400);
  const h = Math.floor((seconds % 86400) / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  let parts = [];
  if (d) parts.push(d + "d");
  if (h) parts.push(h + "h");
  parts.push(m + "m");
  return "Uptime: " + parts.join(" ");
}

function barColor(pct) {
  if (pct < 60) return "var(--good)";
  if (pct < 85) return "var(--warn)";
  return "var(--danger)";
}

async function refresh() {
  try {
    const res = await fetch("/api/stats");
    const d = await res.json();

    document.getElementById("hostname").textContent = d.hostname + " · Monitor";
    document.getElementById("updated").textContent = "En vivo — " + new Date(d.timestamp * 1000).toLocaleTimeString();
    document.getElementById("uptime").textContent = fmtUptime(d.uptime_seconds);

    // CPU
    document.getElementById("cpu-percent").textContent = d.cpu.percent.toFixed(1) + "%";
    const cpuBar = document.getElementById("cpu-bar");
    cpuBar.style.width = d.cpu.percent + "%";
    cpuBar.style.background = barColor(d.cpu.percent);
    document.getElementById("cpu-count").textContent = d.cpu.count + " núcleos";
    document.getElementById("cpu-freq").textContent = d.cpu.freq_current_mhz ? Math.round(d.cpu.freq_current_mhz) + " MHz" : "";

    const coresDiv = document.getElementById("cores");
    coresDiv.innerHTML = "";
    (d.cpu.per_core || []).forEach((pct, idx) => {
      const wrap = document.createElement("div");
      wrap.className = "core";
      wrap.innerHTML = `<div class="bar-bg"><div class="bar-fill" style="height:${pct}%; background:${barColor(pct)}"></div></div><div>${idx}</div>`;
      coresDiv.appendChild(wrap);
    });

    // Memoria
    document.getElementById("mem-percent").textContent = d.memory.percent.toFixed(1) + "%";
    const memBar = document.getElementById("mem-bar");
    memBar.style.width = d.memory.percent + "%";
    memBar.style.background = barColor(d.memory.percent);
    document.getElementById("mem-used").textContent = fmtBytes(d.memory.used) + " usados";
    document.getElementById("mem-total").textContent = fmtBytes(d.memory.total) + " total";

    // Swap
    document.getElementById("swap-percent").textContent = d.swap.percent.toFixed(1) + "%";
    const swapBar = document.getElementById("swap-bar");
    swapBar.style.width = d.swap.percent + "%";
    swapBar.style.background = barColor(d.swap.percent);
    document.getElementById("swap-detail").innerHTML = `<span>${fmtBytes(d.swap.used)} usados</span><span>${fmtBytes(d.swap.total)} total</span>`;

    // Temperatura
    document.getElementById("temp").textContent = d.temperature_c !== null ? d.temperature_c + "°C" : "N/D";
    document.getElementById("throttle").textContent = d.throttle_status ? d.throttle_status : "";

    // Load average
    document.getElementById("load1").textContent = d.cpu.load_avg[0] !== null ? d.cpu.load_avg[0] : "N/D";
    document.getElementById("load-detail").innerHTML = `<span>5m: ${d.cpu.load_avg[1] ?? "-"}</span><span>15m: ${d.cpu.load_avg[2] ?? "-"}</span>`;

    // Red
    document.getElementById("net-sent").textContent = fmtBytes(d.network.bytes_sent);
    document.getElementById("net-recv").textContent = fmtBytes(d.network.bytes_recv);
    document.getElementById("net-packets").textContent = d.network.packets_sent + " / " + d.network.packets_recv;

    // Procesos
    document.getElementById("procs").textContent = d.processes ?? "-";

    // Discos
    const disksDiv = document.getElementById("disks");
    disksDiv.innerHTML = "";
    (d.disks || []).forEach(disk => {
      const item = document.createElement("div");
      item.className = "disk-item";
      item.innerHTML = `
        <div class="row"><strong>${disk.mountpoint}</strong><span>${disk.percent.toFixed(1)}%</span></div>
        <div class="bar-bg"><div class="bar-fill" style="width:${disk.percent}%; background:${barColor(disk.percent)}"></div></div>
        <div class="row"><span class="path">${disk.device} (${disk.fstype})</span><span>${fmtBytes(disk.used)} / ${fmtBytes(disk.total)}</span></div>
      `;
      disksDiv.appendChild(item);
    });

  } catch (e) {
    document.getElementById("updated").textContent = "Error de conexión...";
  }
}

refresh();
setInterval(refresh, 2000);
</script>
</body>
</html>
"""


@app.route("/")
def index():
    return render_template_string(INDEX_HTML)


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)
