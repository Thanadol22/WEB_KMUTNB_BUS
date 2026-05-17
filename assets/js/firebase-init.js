// Firebase JS SDK v9 (Modular) Initialization
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";
import { getFirestore } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js";
import { getDatabase } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-database.js";
import { getAuth } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-auth.js";

// Load config from window object (Injected by PHP in index.php)
const firebaseConfig = window.firebaseConfig || {};

if (!firebaseConfig.apiKey) {
  console.error("Firebase Web Config is missing! Check your .env file and includes/firebase_config.php");
}

// Initialize Firebase
const app = initializeApp(firebaseConfig);
const db = getFirestore(app);
const rtdb = getDatabase(app);
const auth = getAuth(app);

// Simple connection test
console.log("Firebase initialized successfully:", app.name);

export { app, db, auth, rtdb };
