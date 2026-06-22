import { Routes, Route } from 'react-router-dom';
import Layout from './components/Layout';
import Dashboard from './pages/Dashboard';
import Lots from './pages/Lots';

function App() {
  return (
    <Routes>
      <Route path="/" element={<Layout />}>
        <Route index element={<Dashboard />} />
        <Route path="lots" element={<Lots />} />
        {/* We can add Barangay, Imports, etc. later */}
      </Route>
    </Routes>
  );
}

export default App;
