<?php
// Redirect to dashboard if logged in, otherwise show landing page
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}
// If not logged in, show the landing page below
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agro Connect - Connecting Farmers and Experts</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo">
                    <h1><i class="fas fa-seedling"></i> Agro Connect</h1>
                </div>
                <ul class="nav-menu">
                    <li><a href="#home"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="#features"><i class="fas fa-star"></i> Features</a></li>
                    <li><a href="#about"><i class="fas fa-info-circle"></i> About</a></li>
                    <li><a href="#contact"><i class="fas fa-envelope"></i> Contact</a></li>
                    <li><a href="login.php" class="btn"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                    <li><a href="register.php" class="btn"><i class="fas fa-user-plus"></i> Register</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <main>
        <!-- Hero Section -->
        <section id="home" class="hero-section">
            <div class="container">
                <div class="hero-content">
                    <h1>Welcome to Agro Connect</h1>
                    <p>Empowering farmers with technology and expert guidance for sustainable agriculture</p>
                    <div class="hero-buttons">
                        <a href="register.php" class="btn btn-large">Get Started</a>
                        <a href="#features" class="btn btn-large btn-secondary">Learn More</a>
                    </div>
                </div>
              
            </div>
        </section>

       
        <!-- Features Section -->
        <section id="features" class="features-section">
            <div class="container">
                <h2>Why Choose Agro Connect?</h2>
                <p class="section-subtitle">Empowering farmers with technology and expertise for sustainable agriculture</p>
                <div class="features-grid">
                    <div class="feature-card">
                        <i class="fas fa-cloud-sun"></i>
                        <h3>Weather Intelligence</h3>
                        <p>Get accurate weather forecasts and alerts to optimize your farming operations and protect your crops from adverse conditions.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-leaf"></i>
                        <h3>Crop Advisory</h3>
                        <p>Receive personalized crop recommendations based on your soil type, location, and seasonal conditions from certified agricultural experts.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-comments"></i>
                        <h3>Expert Consultation</h3>
                        <p>Connect instantly with agricultural specialists for real-time advice on pest control, irrigation, and crop management.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-tractor"></i>
                        <h3>Equipment Marketplace</h3>
                        <p>Access a comprehensive inventory of farming equipment from tractors to harvesters, available for rent at competitive rates.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-calendar-alt"></i>
                        <h3>Smart Booking</h3>
                        <p>Streamline equipment rental with our intelligent booking system that ensures availability and seamless delivery.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-chart-line"></i>
                        <h3>Farm Analytics</h3>
                        <p>Track your farm's performance with detailed analytics and insights to maximize productivity and profitability.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section id="how-it-works" class="how-it-works-section">
            <div class="container">
                <h2>How It Works</h2>
                <div class="steps-grid">
                    <div class="step-item">
                        <div class="step-number">1</div>
                        <h3>Register & Verify</h3>
                        <p>Create your account as a farmer or agricultural expert. Complete verification to access all platform features.</p>
                    </div>
                    <div class="step-item">
                        <div class="step-number">2</div>
                        <h3>Access Services</h3>
                        <p>Get weather updates, expert advice, and browse our equipment catalog tailored to your farming needs.</p>
                    </div>
                    <div class="step-item">
                        <div class="step-number">3</div>
                        <h3>Book & Connect</h3>
                        <p>Rent equipment, schedule expert consultations, and manage all your agricultural activities in one place.</p>
                    </div>
                </div>
            </div>
        </section>

      

        <!-- About Section -->
        <section id="about" class="about-section">
            <div class="container">
                <div class="about-content">
                    <div class="about-text">
                        <h2>About Agro Connect</h2>
                        <p>Agro Connect is a comprehensive platform designed to bridge the gap between farmers and agricultural experts. Our mission is to empower farmers with cutting-edge technology and expert knowledge to enhance productivity and sustainability in agriculture.</p>
                        <p>Whether you're a small-scale farmer or managing large agricultural operations, Agro Connect provides the tools and support you need to make informed decisions and optimize your farming practices.</p>
                        <a href="register.php" class="btn">Join Our Community</a>
                    </div>
                    <div class="about-image">
                        <img src="img/CombineHarvester.jpg" alt="Modern farming technology">
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Section -->
        <section id="contact" class="contact-section">
            <div class="container">
                <h2>Contact Us</h2>
                <div class="contact-content">
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <h3>Email</h3>
                            <p>support@agroconnect.com</p>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <h3>Phone</h3>
                            <p>+1 (555) 123-4567</p>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <h3>Address</h3>
                            <p>123 Agriculture Street<br>Farming City, FC 12345</p>
                        </div>
                    </div>
                    <div class="contact-form">
                        <h3>Send us a message</h3>
                        <form action="#" method="post">
                            <div class="form-group">
                                <label for="name">Name</label>
                                <input type="text" id="name" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="message">Message</label>
                                <textarea id="message" name="message" rows="5" required></textarea>
                            </div>
                            <button type="submit" class="btn">Send Message</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Agro Connect. All rights reserved.</p>
        </div>
    </footer>

    <style>
        /* Landing Page Specific Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            overflow-x: hidden;
        }

        .hero-section {
            background-image: url(back.png);
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: white;
            padding: 100px 0 80px;
            min-height: 90vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.1;
        }

      .hero-content {
  flex: 1;
  padding: 40px;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(3px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
  color: #fff;
  max-width: 700px;
  
}

.hero-content h1 {
    font-size: 3rem;
   font-weight: 800;
   background: linear-gradient(180deg, #ffffffff, #ffffffd2);

   -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;

}

.hero-content p {
  font-size: 1.1rem;
  opacity: 0.9;
  color:#fff;   
  
  
}

        .hero-buttons {
            display: flex;
            gap: 20px;
            animation: fadeInUp 1s ease-out 0.3s both;
        }

        .btn-large {
            padding: 18px 35px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(44, 85, 48, 0.3);
        }

        .btn-large::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-large:hover::before {
            left: 100%;
        }

        .btn-secondary {
            background-color: transparent;
            border: 2px solid white;
            color: white;
            backdrop-filter: blur(10px);
        }

        .btn-secondary:hover {
            background-color: white;
            color: #2c5530;
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(255,255,255,0.3);
        }

        .hero-image {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 2;
            animation: fadeInRight 1s ease-out 0.5s both;
        }

        .hero-image img {
            max-width: 100%;
            height: auto;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            transition: transform 0.3s ease;
        }

        .hero-image img:hover {
            transform: scale(1.05);
        }



        .section-subtitle {
            text-align: center;
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* How It Works Section */
        .how-it-works-section {
            padding: 100px 0;
            background: white;
        }

        .how-it-works-section h2 {
            text-align: center;
            margin-bottom: 60px;
            color: #2c5530;
            font-size: 2.8rem;
            font-weight: 700;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
        }

        .step-item {
            text-align: center;
            padding: 40px 30px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid rgba(44,85,48,0.1);
            position: relative;
            animation: fadeInUp 1s ease-out both;
        }

        .step-item:nth-child(1) { animation-delay: 0.1s; }
        .step-item:nth-child(2) { animation-delay: 0.2s; }
        .step-item:nth-child(3) { animation-delay: 0.3s; }

        .step-number {
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #2c5530, #4a7c59);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 auto 20px;
            box-shadow: 0 5px 15px rgba(44,85,48,0.3);
        }

        .step-item h3 {
            color: #2c5530;
            margin-bottom: 15px;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .step-item p {
            color: #666;
            line-height: 1.6;
        }

        /* Testimonials Section */
        .testimonials-section {
            padding: 100px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .testimonials-section h2 {
            text-align: center;
            margin-bottom: 60px;
            color: #2c5530;
            font-size: 2.8rem;
            font-weight: 700;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 40px;
        }

        .testimonial-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid rgba(44,85,48,0.1);
            animation: fadeInUp 1s ease-out both;
        }

        .testimonial-card:nth-child(1) { animation-delay: 0.1s; }
        .testimonial-card:nth-child(2) { animation-delay: 0.2s; }

        .testimonial-content p {
            font-size: 1.1rem;
            line-height: 1.7;
            color: #555;
            margin-bottom: 25px;
            font-style: italic;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .testimonial-author img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .testimonial-author h4 {
            color: #2c5530;
            margin: 0 0 5px 0;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .testimonial-author span {
            color: #666;
            font-size: 0.9rem;
        }

        .features-section {
            padding: 100px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            position: relative;
        }

        .features-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(to bottom, rgba(26,77,58,0.1), transparent);
        }

        .features-section h2 {
            text-align: center;
            margin-bottom: 60px;
            color: #2c5530;
            font-size: 2.8rem;
            font-weight: 700;
            position: relative;
        }

        .features-section h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(45deg, #2c5530, #4a7c59);
            border-radius: 2px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 40px;
        }

        .feature-card {
            background: white;
            padding: 40px 30px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(44,85,48,0.1);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(45deg, #2c5530, #4a7c59);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card i {
            font-size: 3.5rem;
            color: #2c5530;
            margin-bottom: 25px;
            transition: transform 0.3s ease;
        }

        .feature-card:hover i {
            transform: scale(1.1);
        }

        .feature-card h3 {
            margin-bottom: 18px;
            color: #2c5530;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .feature-card p {
            color: #666;
            line-height: 1.6;
        }

        .about-section {
            padding: 100px 0;
            background: white;
        }

        .about-content {
            display: flex;
            align-items: center;
            gap: 60px;
            position: relative;
        }

        .about-text {
            flex: 1;
            animation: fadeInLeft 1s ease-out;
        }

        .about-text h2 {
            color: #2c5530;
            margin-bottom: 25px;
            font-size: 2.8rem;
            font-weight: 700;
        }

        .about-text p {
            margin-bottom: 25px;
            line-height: 1.7;
            color: #555;
            font-size: 1.1rem;
        }

        .about-image {
            flex: 1;
            text-align: center;
            animation: fadeInRight 1s ease-out;
        }

        .about-image img {
            max-width: 100%;
            height: auto;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
        }

        .about-image img:hover {
            transform: scale(1.03);
        }

        .contact-section {
            padding: 100px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .contact-section h2 {
            text-align: center;
            margin-bottom: 60px;
            color: #2c5530;
            font-size: 2.8rem;
            font-weight: 700;
        }

        .contact-content {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 60px;
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 40px;
        }

        .contact-item {
            text-align: center;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .contact-item:hover {
            transform: translateY(-5px);
        }

        .contact-item i {
            font-size: 2.5rem;
            color: #2c5530;
            margin-bottom: 15px;
        }

        .contact-item h3 {
            margin-bottom: 12px;
            color: #2c5530;
            font-size: 1.2rem;
        }

        .contact-item p {
            color: #666;
            margin: 0;
        }

        .contact-form {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            border: 1px solid rgba(44,85,48,0.1);
        }

        .contact-form h3 {
            margin-bottom: 30px;
            color: #2c5530;
            text-align: center;
            font-size: 1.6rem;
        }

        .contact-form .form-group {
            margin-bottom: 20px;
        }

        .contact-form input,
        .contact-form textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .contact-form input:focus,
        .contact-form textarea:focus {
            outline: none;
            border-color: #2c5530;
            box-shadow: 0 0 0 3px rgba(44,85,48,0.1);
        }

        footer {
            background: linear-gradient(135deg, #1a4d3a 0%, #2c5530 100%);
            color: white;
            text-align: center;
            padding: 30px 0;
            position: relative;
        }

        footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(45deg, #4a7c59, #2c5530);
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Enhanced Navigation */
        .navbar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .navbar.scrolled {
            background: rgba(255,255,255,0.98);
            box-shadow: 0 5px 30px rgba(0,0,0,0.15);
        }

        .nav-logo h1 {
            color: #2c5530 !important;
            font-weight: 800;
        }

        .nav-menu a {
            color: #2c5530 !important;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-menu a:hover {
            color: #fff !important;
        }

        .nav-menu .btn {
            background: linear-gradient(45deg, #2c5530, #4a7c59);
            color: white !important;
            border-radius: 25px;
            padding: 8px 20px;
            margin-left: 15px;
        }

        .nav-menu .btn:hover {
            background: #4a7c59;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44,85,48,0.3);
        }

        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }



        @media (max-width: 768px) {
            .hero-section {
                flex-direction: column;
                text-align: center;
                padding: 60px 0 40px;
            }

            .hero-content {
                padding-right: 0;
                margin-bottom: 40px;
            }

            .hero-content h1 {
                font-size: 2.5rem;
            }

            .hero-content p {
                font-size: 1.1rem;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .features-section h2,
            .about-text h2,
            .contact-section h2 {
                font-size: 2.2rem;
            }

            .about-content,
            .contact-content {
                flex-direction: column;
                gap: 40px;
            }

            .features-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .contact-content {
                grid-template-columns: 1fr;
            }

            .contact-form {
                padding: 30px 20px;
            }

            .nav-menu {
                flex-direction: column;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                box-shadow: 0 5px 20px rgba(0,0,0,0.1);
                transform: translateY(-100%);
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            }

            .nav-menu.active {
                transform: translateY(0);
                opacity: 1;
                visibility: visible;
            }
        }
    </style>

    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>
