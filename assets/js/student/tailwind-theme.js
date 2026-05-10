tailwind.config = {
    theme: {
        extend: {
            fontFamily: {
                display: ['Syne', 'sans-serif'],
                body: ['DM Sans', 'sans-serif'],
                mono: ['DM Mono', 'monospace'],
            },
            colors: {
                brand: {
                    50: '#f0f4ff',
                    100: '#e0e9ff',
                    200: '#c7d5fe',
                    300: '#a5b4fc',
                    400: '#818cf8',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                    800: '#3730a3',
                    900: '#1e1b4b',
                },
                surface: {
                    50: '#f8fafc',
                    100: '#f1f5f9',
                    200: '#e2e8f0',
                    300: '#cbd5e1',
                    800: '#1e293b',
                    900: '#0f172a',
                    950: '#020617',
                }
            },
            animation: {
                'fade-up': 'fadeUp 0.5s ease forwards',
                'fade-in': 'fadeIn 0.4s ease forwards',
                'slide-in': 'slideIn 0.4s ease forwards',
                'pulse-slow': 'pulse 3s cubic-bezier(0.4,0,0.6,1) infinite',
                'float': 'float 6s ease-in-out infinite',
                'count-up': 'countUp 1s ease forwards',
            },
            keyframes: {
                fadeUp: {
                    '0%': { opacity: '0', transform: 'translateY(20px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                slideIn: {
                    '0%': { opacity: '0', transform: 'translateX(-20px)' },
                    '100%': { opacity: '1', transform: 'translateX(0)' },
                },
                float: {
                    '0%, 100%': { transform: 'translateY(0px)' },
                    '50%': { transform: 'translateY(-8px)' },
                }
            }
        }
    }
};
