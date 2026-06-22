import { useCallback, useState, useEffect } from 'react';
import { Search, Plus, FileSpreadsheet } from 'lucide-react';

export default function Lots() {
  const [lots, setLots] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  
  const fetchLots = useCallback(() => {
    setLoading(true);
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (statusFilter) params.append('status', statusFilter);

    fetch(`http://192.168.254.104/Land-Invertory-System/api/get_lots.php?${params.toString()}`)
      .then(res => res.json())
      .then(json => {
        if (json.status === 'success') {
          setLots(json.data);
        }
        setLoading(false);
      })
      .catch(() => setLoading(false));
  }, [search, statusFilter]);

  // Debounced search
  useEffect(() => {
    const timer = setTimeout(() => {
      fetchLots();
    }, 300);
    return () => clearTimeout(timer);
  }, [fetchLots]);

  return (
    <div className="space-y-6 animate-in fade-in duration-500">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
        <div>
          <h1 className="text-3xl font-bold text-white mb-2">Lots Directory</h1>
          <p className="text-gray-400">View and manage all recorded land lots.</p>
        </div>
        <div className="flex gap-3 w-full md:w-auto">
            <button className="flex-1 md:flex-none flex items-center justify-center gap-2 bg-primary/10 text-primary px-4 py-2 rounded-xl font-medium hover:bg-primary/20 transition">
                <FileSpreadsheet className="w-4 h-4" /> Export
            </button>
            <button className="flex-1 md:flex-none flex items-center justify-center gap-2 bg-primary text-white px-4 py-2 rounded-xl font-bold hover:bg-primary/90 transition shadow-lg shadow-primary/25">
                <Plus className="w-5 h-5" /> New Lot
            </button>
        </div>
      </div>

      <div className="bg-panel border border-border rounded-2xl shadow-lg overflow-hidden">
        {/* Filters */}
        <div className="p-4 border-b border-border bg-black/20 flex flex-col md:flex-row gap-4">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" />
            <input 
              type="text" 
              placeholder="Search lot no, survey no, claimant..." 
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full bg-black/40 border border-white/10 rounded-xl py-2 pl-10 pr-4 text-white focus:outline-none focus:border-primary/50 transition"
            />
          </div>
          <div className="flex gap-3">
            <select 
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="bg-black/40 border border-white/10 rounded-xl py-2 px-4 text-white focus:outline-none focus:border-primary/50 appearance-none min-w-[150px]"
            >
              <option value="">All Statuses</option>
              <option value="Titled">Titled</option>
              <option value="Applied">Applied</option>
              <option value="Unapplied">Unapplied</option>
              <option value="Conflict">Conflict</option>
            </select>
          </div>
        </div>

        {/* Table */}
        <div className="overflow-x-auto min-h-[400px]">
          {loading ? (
            <div className="flex justify-center items-center h-64"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>
          ) : (
            <table className="w-full text-left text-sm whitespace-nowrap">
              <thead className="bg-black/40 text-gray-400">
                <tr>
                  <th className="px-6 py-4 font-medium">Lot No</th>
                  <th className="px-6 py-4 font-medium">Survey No</th>
                  <th className="px-6 py-4 font-medium">Claimant</th>
                  <th className="px-6 py-4 font-medium">Location</th>
                  <th className="px-6 py-4 font-medium">Area (sqm)</th>
                  <th className="px-6 py-4 font-medium">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border/50">
                {lots.map((lot) => (
                  <tr key={lot.id} className="hover:bg-white/5 transition-colors group cursor-pointer">
                    <td className="px-6 py-4 font-bold text-white">{lot.lot_no}</td>
                    <td className="px-6 py-4 text-gray-300 font-mono text-xs">{lot.survey_no || '-'}</td>
                    <td className="px-6 py-4 text-gray-300">{lot.claimant || 'Unknown'}</td>
                    <td className="px-6 py-4 text-gray-400">
                        <span className="text-gray-300">{lot.barangay_name}</span>
                        <span className="text-gray-500 mx-1">•</span>
                        <span>{lot.municipality_name}</span>
                    </td>
                    <td className="px-6 py-4 text-gray-300">{Number(lot.area_sqm).toLocaleString()}</td>
                    <td className="px-6 py-4">
                        <span className={`px-2 py-1 rounded-md text-xs font-bold border ${
                            lot.status === 'Titled' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' :
                            lot.status === 'Applied' ? 'bg-blue-500/10 text-blue-400 border-blue-500/20' :
                            lot.status === 'Conflict' ? 'bg-red-500/10 text-red-400 border-red-500/20' :
                            'bg-gray-500/10 text-gray-400 border-gray-500/20'
                        }`}>
                            {lot.status}
                        </span>
                    </td>
                  </tr>
                ))}
                {lots.length === 0 && (
                    <tr>
                        <td colSpan="6" className="px-6 py-12 text-center text-gray-500">No lots found matching your criteria.</td>
                    </tr>
                )}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  );
}
