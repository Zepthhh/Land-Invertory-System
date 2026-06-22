import { useState, useEffect } from 'react';
import { Map, FileText } from 'lucide-react';
import { PieChart, Pie, Cell, Tooltip as RechartsTooltip, ResponsiveContainer } from 'recharts';

export default function Dashboard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    fetch('http://192.168.254.104/Land-Invertory-System/api/dashboard_stats.php')
      .then(res => res.json())
      .then(json => {
        if (json.status === 'success') {
          setData(json.data);
        } else {
          setError(json.message);
        }
        setLoading(false);
      })
      .catch(err => {
        setError(err.message);
        setLoading(false);
      });
  }, []);

  if (loading) return <div className="flex justify-center items-center h-64"><div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div></div>;
  if (error) return <div className="p-4 bg-red-500/10 border border-red-500/20 text-red-400 rounded-xl">{error}</div>;

  const COLORS = {
    'Titled': '#10b981',
    'Applied': '#3b82f6',
    'Unapplied': '#6b7280',
    'Conflict': '#ef4444'
  };

  const chartData = Object.entries(data.statusData).map(([name, val]) => ({
    name,
    value: val.count
  })).filter(d => d.value > 0);

  return (
    <div className="space-y-8 animate-in fade-in duration-500">
      <div>
        <h1 className="text-3xl font-bold text-white mb-2">Dashboard</h1>
        <p className="text-gray-400">Barangay land overview with exact remaining and balance computations.</p>
      </div>

      {/* Top Stat Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div className="bg-panel border border-border p-6 rounded-2xl shadow-lg relative overflow-hidden group">
          <div className="absolute -right-4 -top-4 w-24 h-24 bg-primary/10 rounded-full blur-2xl group-hover:bg-primary/20 transition-all"></div>
          <div className="flex justify-between items-start mb-4">
            <div>
              <p className="text-gray-400 text-sm font-medium mb-1">Total Lots Recorded</p>
              <h3 className="text-3xl font-bold text-white">{data.totalLotsCount}</h3>
            </div>
            <div className="p-3 bg-primary/10 rounded-xl text-primary">
              <FileText className="w-6 h-6" />
            </div>
          </div>
        </div>
        
        {data.municipalityCards.map((mun, idx) => (
          <div key={idx} className="bg-panel border border-border p-6 rounded-2xl shadow-lg relative overflow-hidden group">
             <div className="absolute -right-4 -top-4 w-24 h-24 bg-blue-500/10 rounded-full blur-2xl group-hover:bg-blue-500/20 transition-all"></div>
             <div className="flex justify-between items-start mb-4">
              <div>
                <p className="text-gray-400 text-sm font-medium mb-1">{mun.name}</p>
                <h3 className="text-3xl font-bold text-white">{mun.barangay_count}</h3>
                <p className="text-xs text-gray-500 mt-1">Barangays</p>
              </div>
              <div className="p-3 bg-blue-500/10 rounded-xl text-blue-400">
                <Map className="w-6 h-6" />
              </div>
            </div>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Table Section */}
        <div className="lg:col-span-2 space-y-6">
          <div className="bg-panel border border-border rounded-2xl shadow-lg overflow-hidden">
            <div className="p-6 border-b border-border">
              <h2 className="text-xl font-bold text-white">Barangay Land Summary</h2>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm whitespace-nowrap">
                <thead className="bg-black/20 text-gray-400">
                  <tr>
                    <th className="px-6 py-4 font-medium">Municipality</th>
                    <th className="px-6 py-4 font-medium">Barangay</th>
                    <th className="px-6 py-4 font-medium">Total Area (sqm)</th>
                    <th className="px-6 py-4 font-medium">Recorded Lots</th>
                    <th className="px-6 py-4 font-medium">Remaining (sqm)</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border/50">
                  {data.barangaySummaries.map((b, i) => (
                    <tr key={i} className="hover:bg-white/5 transition-colors cursor-pointer group">
                      <td className="px-6 py-4 text-gray-300 group-hover:text-white transition-colors">{b.municipality_name}</td>
                      <td className="px-6 py-4 font-medium text-white">{b.name}</td>
                      <td className="px-6 py-4 text-gray-300">{Number(b.total_area_sqm).toLocaleString()}</td>
                      <td className="px-6 py-4 text-gray-300">
                        <span className="px-2 py-1 bg-white/5 rounded-md border border-white/10">{b.total_lots}</span>
                      </td>
                      <td className="px-6 py-4 text-gray-300 font-mono text-xs">{Number(b.remaining_balance).toLocaleString()}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        {/* Sidebar Widgets Section */}
        <div className="space-y-6">
          {/* Chart */}
          <div className="bg-panel border border-border rounded-2xl shadow-lg p-6">
            <h2 className="text-lg font-bold text-white mb-6">Lot Status Breakdown</h2>
            <div className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={chartData}
                    cx="50%"
                    cy="50%"
                    innerRadius={60}
                    outerRadius={80}
                    paddingAngle={5}
                    dataKey="value"
                    stroke="none"
                  >
                    {chartData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={COLORS[entry.name] || '#999'} />
                    ))}
                  </Pie>
                  <RechartsTooltip 
                    contentStyle={{ backgroundColor: 'rgba(18,27,22,0.9)', borderColor: 'rgba(255,255,255,0.1)', borderRadius: '12px', color: '#fff' }}
                    itemStyle={{ color: '#fff' }}
                  />
                </PieChart>
              </ResponsiveContainer>
            </div>
            <div className="grid grid-cols-2 gap-4 mt-6">
              {Object.entries(data.statusData).map(([status, info]) => (
                <div key={status} className="flex items-center gap-3 bg-black/20 p-3 rounded-xl border border-white/5">
                  <div className="w-3 h-3 rounded-full shadow-sm" style={{ backgroundColor: COLORS[status] || '#999' }}></div>
                  <div>
                    <p className="text-xs text-gray-400">{status}</p>
                    <p className="font-bold text-white">{info.count}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
