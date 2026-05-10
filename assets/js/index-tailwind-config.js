tailwind.config = {
    theme: {
        extend: {
            fontFamily: {
                display: ['Space Grotesk', 'sans-serif'],
                body: ['Manrope', 'sans-serif'],
            },
            colors: {
                ink: '#07111f',
                panel: '#0d1726',
            },
            boxShadow: {
                glow: '0 0 0 1px rgba(129, 140, 248, 0.18), 0 25px 80px rgba(79, 70, 229, 0.18)',
                emeraldGlow: '0 0 0 1px rgba(16, 185, 129, 0.18), 0 25px 70px rgba(16, 185, 129, 0.16)',
            },
            keyframes: {
                float: {
                    '0%, 100%': { transform: 'translate3d(0,0,0)' },
                    '50%': { transform: 'translate3d(0,-18px,0)' },
                },
                pulseGlow: {
                    '0%, 100%': { opacity: '0.45', transform: 'scale(1)' },
                    '50%': { opacity: '0.9', transform: 'scale(1.08)' },
                },
                marquee: {
                    '0%': { transform: 'translateX(0)' },
                    '100%': { transform: 'translateX(-33.333%)' },
                },
                gradientShift: {
                    '0%, 100%': { backgroundPosition: '0% 50%' },
                    '50%': { backgroundPosition: '100% 50%' },
                },
                spinSlow: {
                    from: { transform: 'rotate(0deg)' },
                    to: { transform: 'rotate(360deg)' },
                }
            },
            animation: {
                float: 'float 8s ease-in-out infinite',
                pulseGlow: 'pulseGlow 4.5s ease-in-out infinite',
                marquee: 'marquee 22s linear infinite',
                gradientShift: 'gradientShift 16s ease infinite',
                spinSlow: 'spinSlow 28s linear infinite',
            }
        }
    }
};
