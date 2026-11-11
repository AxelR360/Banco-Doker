const API_URL = (import.meta.env.VITE_API_URL || (window.location.hostname === 'banco-dokr.onrender.com' ? 'https://banco-bakend.onrender.com' : '')).replace(/\/$/,'')

export default API_URL