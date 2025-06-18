<p align="center">
  <!-- Laravel modern logo -->
  <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/laravel/laravel-original-wordmark.svg" width="120" alt="Laravel Logo" style="margin-right: 30px;">
  <!-- MySQL modern logo -->
  <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/mysql/mysql-original-wordmark.svg" width="100" alt="MySQL Logo" style="margin-right: 30px;">
  <!-- PHP modern logo -->
  <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/php/php-original.svg" width="100" alt="PHP Logo">
</p>

<h1 align="center">EduGuard Backend</h1>
<p align="center">
  <b>Professional, Secure, and Scalable Backend for E-Learning & AI Proctoring</b>
</p>

---

## 📝 Application Overview

**EduGuard** is a modern backend system for an e-learning platform, built with Laravel (PHP). It provides a comprehensive RESTful API for managing users, courses, exams, and integrates with advanced AI/ML services for smart proctoring, face detection, and cheating prevention. The backend is designed for scalability, security, and easy integration with any frontend or mobile application.

### **Key Features**
- User authentication & authorization (JWT/Sanctum)
- Course & exam management
- Student progress tracking
- Integration with Python-based ML APIs for:
  - Face recognition
  - Head pose & gaze detection
  - Cheating detection
- Role-based access (admin, instructor, student)
- Secure RESTful API endpoints
- Modern, clean codebase following Laravel best practices

---

## 🚀 Tech Stack

- **Framework:** Laravel (PHP)
- **Database:** MySQL
- **Authentication:** Laravel Sanctum/JWT
- **AI Integration:** Python ML APIs (via HTTP)
- **Other:** Composer, Artisan CLI

---

## ⚡ Getting Started

1. **Clone the repository:**
   ```bash
   git clone https://github.com/your-org/eduguard-backend.git
   cd eduguard-backend
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Copy and configure environment variables:**
   ```bash
   cp .env.example .env
   # Edit .env with your DB and app settings
   ```

4. **Generate application key:**
   ```bash
   php artisan key:generate
   ```

5. **Run migrations:**
   ```bash
   php artisan migrate
   ```

6. **Start the development server:**
   ```bash
   php artisan serve
   ```

---

## 🗂️ Project Structure & Code Overview

- **app/Http/Controllers/**  
  Handles API requests (e.g., `UserController`, `CourseController`, `ExamController`).

- **app/Models/**  
  Eloquent models for database tables (e.g., `User`, `Course`, `Exam`).

- **routes/api.php**  
  All API endpoints and their controllers.

- **app/Http/Middleware/**  
  Middleware for authentication, authorization, and request filtering.

- **ML Integration**  
  Communicates with external Python ML APIs for proctoring and analytics.

---

## 📚 Example API Endpoints

### Register a New User

```http
POST /api/register
Content-Type: application/json

{
  "name": "Ahmed",
  "email": "ahmed@email.com",
  "password": "password"
}
```

### Login

```http
POST /api/login
Content-Type: application/json

{
  "email": "ahmed@email.com",
  "password": "password"
}
```

### Create a New Course

```http
POST /api/courses
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Math 101",
  "description": "Basic math course"
}
```

---

## 🔒 Security & Best Practices

- All sensitive endpoints require authentication (token-based).
- Input validation and error handling are implemented.
- Environment variables are used for all credentials and secrets.

---

## 🤝 Contribution

Contributions are welcome! Please fork the repo and submit a pull request with a clear description of your changes.

---

## 📄 License

This project is open-sourced under the [MIT license](https://opensource.org/licenses/MIT).

---

<p align="center">
  <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/laravel/laravel-original.svg" width="60" alt="Laravel Logo">
  <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/mysql/mysql-original.svg" width="60" alt="MySQL Logo">
  <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/php/php-original.svg" width="60" alt="PHP Logo">
</p>
