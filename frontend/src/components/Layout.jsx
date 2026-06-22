import { useState } from 'react';
import { Outlet, NavLink } from 'react-router-dom';
import { Home, ClipboardList, Menu } from 'lucide-react';

export default function Layout() {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const navItems = [
    { name: 'Dashboard', path: '/', icon: Home },
    { name: 'Lots', path: '/lots', icon: ClipboardList },
  ];

  return (
    <div className="flex min-h-screen bg-bg text-gray-100">
      {/* Mobile Overlay */}
      {mobileMenuOpen && (
        <div 
          className="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 md:hidden"
          onClick={() => setMobileMenuOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside className={`fixed inset-y-0 left-0 z-50 w-64 bg-panel backdrop-blur-xl border-r border-border transform transition-transform duration-300 ease-in-out md:translate-x-0 md:static ${mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'}`}>
        <div className="flex flex-col h-full p-6">
          <div className="flex flex-col items-center text-center mb-8">
            <div className="w-24 h-24 bg-white rounded-full p-1 mb-4 shadow-[0_8px_25px_rgba(0,0,0,0.5),0_0_0_3px_rgba(16,185,129,0.4)] relative overflow-hidden">
                <img src="/assets/img/logo.png" alt="Logo" className="w-full h-full object-cover rounded-full scale-[1.45] -translate-x-[1.5%]" />
            </div>
            <h1 className="text-2xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-emerald-300 to-emerald-400">DENR CENRO</h1>
            <p className="text-gray-400 text-sm mt-1">Land Inventory & RLTA</p>
          </div>

          <nav className="space-y-2 flex-1">
            {navItems.map((item) => (
              <NavLink
                key={item.name}
                to={item.path}
                onClick={() => setMobileMenuOpen(false)}
                className={({ isActive }) =>
                  `flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 ${
                    isActive
                      ? 'bg-primary/10 text-primary shadow-[inset_3px_0_0_rgba(16,185,129,1)]'
                      : 'text-gray-400 hover:bg-white/5 hover:text-white'
                  }`
                }
              >
                <item.icon className="w-5 h-5" />
                <span className="font-medium">{item.name}</span>
              </NavLink>
            ))}
          </nav>
        </div>
      </aside>

      {/* Main Content */}
      <main className="flex-1 flex flex-col min-h-screen w-full md:w-[calc(100%-16rem)]">
        {/* Mobile Header */}
        <header className="md:hidden flex items-center justify-between p-4 bg-panel border-b border-border sticky top-0 z-30">
          <div className="flex items-center gap-3">
            <img src="/assets/img/logo.png" alt="Logo" className="w-8 h-8 rounded-full object-cover scale-[1.45]" />
            <span className="font-bold text-lg">DENR CENRO</span>
          </div>
          <button onClick={() => setMobileMenuOpen(true)} className="p-2 text-gray-300 hover:text-white">
            <Menu className="w-6 h-6" />
          </button>
        </header>

        <div className="p-6 md:p-8 flex-1 overflow-x-hidden">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
