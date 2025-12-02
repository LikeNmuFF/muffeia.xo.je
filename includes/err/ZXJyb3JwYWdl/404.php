<?php
// Set HTTP response code to 404
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Lost in Space - MUFFEIA</title>
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../logo/m-blues.png" type="image/png">
    <style>
        :root {
            --primary: #7c3aed;
            --primary-dark: #6d28d9;
            --primary-light: #8b5cf6;
            --secondary: #f59e0b;
            --secondary-light: #fbbf24;
            --accent: #10b981;
            --neon: #00f3ff;
            --dark: #1e293b;
            --darker: #0f172a;
            --light: #f8fafc;
            --gray: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --glow: 0 0 20px rgba(124, 58, 237, 0.5);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background: linear-gradient(135deg, var(--darker) 0%, #000 100%);
            min-height: 100vh;
            min-height: 100dvh;
            color: var(--light);
            overflow-x: hidden;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            perspective: 1000px;
            touch-action: manipulation;
        }

        /* Animated cosmic background */
        .cosmic-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(124, 58, 237, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(245, 158, 11, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(16, 185, 129, 0.05) 0%, transparent 50%);
            animation: cosmicPulse 8s ease-in-out infinite;
        }

        @keyframes cosmicPulse {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 0.8; }
        }

        .floating-element {
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
            pointer-events: none;
            z-index: 0;
            animation-duration: 8s;
            animation-iteration-count: infinite;
            animation-timing-function: ease-in-out;
            filter: blur(1px);
        }

        .floating-element:nth-child(odd) {
            animation-name: float;
        }

        .floating-element:nth-child(even) {
            animation-name: float-reverse;
        }

        /* Mobile-optimized floating elements */
        .floating-element:nth-child(1) {
            width: min(250px, 60vw);
            height: min(250px, 60vw);
            background: var(--primary);
            top: -20%;
            right: -20%;
            animation-delay: 0s;
            box-shadow: 0 0 50px var(--primary);
        }

        .floating-element:nth-child(2) {
            width: min(200px, 50vw);
            height: min(200px, 50vw);
            background: var(--primary-light);
            bottom: -15%;
            left: -15%;
            animation-delay: 1s;
            box-shadow: 0 0 40px var(--primary-light);
        }

        .floating-element:nth-child(3) {
            width: min(150px, 40vw);
            height: min(150px, 40vw);
            background: var(--secondary);
            top: 15%;
            left: 5%;
            animation-delay: 2s;
            box-shadow: 0 0 30px var(--secondary);
        }

        .floating-element:nth-child(4) {
            width: min(100px, 30vw);
            height: min(100px, 30vw);
            background: var(--accent);
            bottom: 20%;
            right: 8%;
            animation-delay: 3s;
            box-shadow: 0 0 20px var(--accent);
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg) translateZ(-100px); opacity: 0.1; }
            50% { transform: translateY(-10px) rotate(5deg) translateZ(50px); opacity: 0.2; }
        }

        @keyframes float-reverse {
            0%, 100% { transform: translateY(0) rotate(0deg) translateZ(-100px); opacity: 0.1; }
            50% { transform: translateY(10px) rotate(-5deg) translateZ(50px); opacity: 0.2; }
        }

        .error-container {
            width: 100%;
            max-width: min(500px, 95vw);
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: min(60px, 8vw) min(40px, 5vw);
            box-shadow: 
                var(--shadow-lg),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            position: relative;
            z-index: 1;
            animation: slideInUp 0.8s ease-out;
            transform-style: preserve-3d;
            transition: transform 0.3s ease-out;
        }

        .error-container::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--primary), var(--secondary), var(--accent), var(--primary));
            border-radius: 22px;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.5s ease;
            animation: borderGlow 3s ease-in-out infinite;
        }

        @keyframes borderGlow {
            0%, 100% { opacity: 0.1; filter: hue-rotate(0deg); }
            50% { opacity: 0.3; filter: hue-rotate(45deg); }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .error-icon {
            font-size: min(5rem, 15vw);
            color: var(--secondary);
            margin-bottom: min(25px, 6vw);
            animation: bounce 2s infinite, glow 3s ease-in-out infinite;
            transform: translateZ(40px);
            text-shadow: 0 0 30px var(--secondary);
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0) translateZ(40px); }
            40% { transform: translateY(-10px) translateZ(40px); }
            60% { transform: translateY(-5px) translateZ(40px); }
        }

        @keyframes glow {
            0%, 100% { text-shadow: 0 0 20px var(--secondary); }
            50% { text-shadow: 0 0 30px var(--secondary), 0 0 40px var(--secondary-light); }
        }

        .error-code {
            font-size: min(8rem, 22vw);
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 50%, var(--accent) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: min(10px, 3vw);
            line-height: 1;
            transform: translateZ(60px);
            text-shadow: 0 5px 30px rgba(0,0,0,0.3);
            animation: codePulse 4s ease-in-out infinite;
        }

        @keyframes codePulse {
            0%, 100% { transform: translateZ(60px) scale(1); }
            50% { transform: translateZ(60px) scale(1.03); }
        }

        .error-title {
            font-size: min(2.2rem, 7vw);
            font-weight: 800;
            margin-bottom: min(20px, 5vw);
            color: var(--light);
            transform: translateZ(30px);
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            line-height: 1.2;
        }

        .error-message {
            color: var(--gray);
            margin-bottom: min(40px, 8vw);
            line-height: 1.6;
            font-size: min(1.2rem, 4.5vw);
            transform: translateZ(30px);
            font-weight: 300;
            padding: 0 min(10px, 2vw);
        }

        .btn-group {
            display: flex;
            gap: min(20px, 4vw);
            justify-content: center;
            flex-wrap: wrap;
            transform: translateZ(20px);
        }

        .btn {
            padding: min(16px, 4vw) min(32px, 6vw);
            border: none;
            border-radius: 14px;
            font-size: min(1.1rem, 4vw);
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: min(12px, 3vw);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
            min-height: 54px;
            flex: 1;
            min-width: min(200px, 45vw);
            justify-content: center;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
        }

        .btn-primary:hover, .btn-primary:active {
            transform: translateY(-3px) translateZ(20px);
            box-shadow: 0 12px 30px rgba(124, 58, 237, 0.6);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: var(--light);
            border: 1px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .btn-secondary:hover, .btn-secondary:active {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-3px) translateZ(20px);
            box-shadow: 0 12px 30px rgba(255, 255, 255, 0.1);
        }

        /* Particle effects - reduced on mobile */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .particle {
            position: absolute;
            width: 2px;
            height: 2px;
            background: var(--neon);
            border-radius: 50%;
            animation: particleFloat 8s infinite linear;
            opacity: 0;
        }

        @keyframes particleFloat {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }

        /* Mobile-specific optimizations */
        @media (max-width: 480px) {
            body {
                padding: 12px;
                align-items: flex-start;
                padding-top: min(10vh, 60px);
            }

            .error-container {
                padding: min(40px, 10vw) min(25px, 6vw);
                border-radius: 18px;
            }

            .error-icon {
                font-size: min(4rem, 18vw);
                margin-bottom: min(20px, 8vw);
            }

            .error-code {
                font-size: min(6rem, 25vw);
                margin-bottom: min(5px, 4vw);
            }

            .error-title {
                font-size: min(1.8rem, 8vw);
                margin-bottom: min(15px, 6vw);
            }

            .error-message {
                font-size: min(1.1rem, 5vw);
                margin-bottom: min(30px, 10vw);
                line-height: 1.5;
            }

            .btn-group {
                flex-direction: column;
                gap: min(15px, 4vw);
                width: 100%;
            }

            .btn {
                width: 100%;
                min-width: auto;
                padding: min(14px, 5vw) min(24px, 8vw);
                min-height: 50px;
                font-size: min(1rem, 4.5vw);
            }

            /* Reduce floating elements on very small screens */
            .floating-element:nth-child(3),
            .floating-element:nth-child(4) {
                display: none;
            }
        }

        @media (max-width: 320px) {
            .error-container {
                padding: 30px 20px;
            }
            
            .btn {
                min-height: 48px;
                font-size: 0.9rem;
            }
            
            .error-message {
                font-size: 1rem;
            }
        }

        /* Reduce motion for accessibility */
        @media (prefers-reduced-motion: reduce) {
            .floating-element,
            .error-icon,
            .error-code,
            .particle {
                animation: none;
            }
            
            .btn {
                transition: none;
            }
        }

        /* Orientation specific adjustments */
        @media (max-height: 600px) and (orientation: landscape) {
            body {
                padding: 10px;
                align-items: flex-start;
                padding-top: 20px;
            }
            
            .error-container {
                padding: 30px 25px;
                max-width: 90vw;
            }
            
            .error-icon {
                font-size: 3rem;
                margin-bottom: 15px;
            }
            
            .error-code {
                font-size: 4rem;
                margin-bottom: 5px;
            }
            
            .error-title {
                font-size: 1.4rem;
                margin-bottom: 10px;
            }
            
            .error-message {
                margin-bottom: 20px;
                font-size: 1rem;
            }
            
            .btn {
                padding: 12px 20px;
                min-height: 44px;
            }
        }
    </style>
</head>
<body>
    <div class="cosmic-bg"></div>
    <div class="floating-element"></div>
    <div class="floating-element"></div>
    <div class="floating-element"></div>
    <div class="floating-element"></div>
    <div class="particles" id="particles"></div>
    
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-rocket"></i>
        </div>
        
        <div class="error-code">404</div>
        <h1 class="error-title">Lost in Space</h1>
        
        <p class="error-message">
            The cosmic coordinates you're searching for don't exist in our galaxy. 
            Let's navigate back to familiar territory.
        </p>

        <div class="btn-group">
            <a href="/" class="btn btn-primary">
                <i class="fas fa-home"></i> Return to Homebase
            </a>
            
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Previous Orbit
            </a>
            
            <a href="/community/contact.php" class="btn btn-secondary">
                <i class="fas fa-satellite"></i> Mission Control
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile-optimized particle effects
            const particlesContainer = document.getElementById('particles');
            const isMobile = window.innerWidth < 768;
            const particleCount = isMobile ? 20 : 50;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + 'vw';
                particle.style.animationDelay = Math.random() * 8 + 's';
                particle.style.animationDuration = (Math.random() * 4 + 6) + 's';
                particlesContainer.appendChild(particle);
            }

            // Mobile-optimized tilt effect
            const container = document.querySelector('.error-container');
            if (container && !isMobile) { // Only enable tilt on non-mobile devices
                const maxRotate = 8;
                const maxTranslate = 10;

                document.addEventListener('mousemove', (e) => {
                    const { clientX, clientY } = e;
                    const { innerWidth, innerHeight } = window;
                    
                    const rotateY = (clientX / innerWidth - 0.5) * 2 * maxRotate;
                    const rotateX = (clientY / innerHeight - 0.5) * -2 * maxRotate;
                    const translateX = (clientX / innerWidth - 0.5) * 2 * maxTranslate;
                    const translateY = (clientY / innerHeight - 0.5) * 2 * maxTranslate;

                    container.style.transition = 'transform 0.1s ease-out';
                    container.style.transform = `
                        rotateX(${rotateX}deg) 
                        rotateY(${rotateY}deg)
                        translateX(${translateX}px)
                        translateY(${translateY}px)
                    `;
                });

                document.addEventListener('mouseleave', () => {
                    container.style.transition = 'transform 0.8s cubic-bezier(0.23, 1, 0.32, 1)';
                    container.style.transform = `rotateX(0deg) rotateY(0deg) translateX(0px) translateY(0px)`;
                });
            }

            // Touch-optimized button effects
            document.querySelectorAll('.btn').forEach(btn => {
                let touchTimeout;
                
                btn.addEventListener('touchstart', function(e) {
                    // Prevent double-tap zoom
                    if (e.touches.length > 1) {
                        e.preventDefault();
                        return;
                    }
                    
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const touch = e.touches[0];
                    const size = Math.max(rect.width, rect.height);
                    const x = touch.clientX - rect.left - size / 2;
                    const y = touch.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255,255,255,0.6);
                        transform: scale(0);
                        animation: ripple 0.6s linear;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                        pointer-events: none;
                    `;
                    
                    this.appendChild(ripple);
                    
                    touchTimeout = setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });

                btn.addEventListener('touchend', function() {
                    clearTimeout(touchTimeout);
                    const ripple = this.querySelector('span');
                    if (ripple) {
                        ripple.remove();
                    }
                });

                btn.addEventListener('touchmove', function(e) {
                    clearTimeout(touchTimeout);
                    const ripple = this.querySelector('span');
                    if (ripple) {
                        ripple.remove();
                    }
                });
            });

            // Add ripple animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);

            // Handle orientation changes
            window.addEventListener('orientationchange', function() {
                setTimeout(() => {
                    window.scrollTo(0, 0);
                }, 100);
            });
        });
    </script>
</body>
</html>