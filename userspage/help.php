<?php
session_start();
require_once('../auth_guard.php');
guard('user');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Help & FAQ — Hungry Wheels</title>

<script src="https://cdn.tailwindcss.com"></script>

<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

<style>
body{
    background:#080d14;
    font-family:'DM Sans',sans-serif;
}

h1,h2,h3{
    font-family:'Syne',sans-serif;
}

.faq-item{
    border-bottom:1px solid rgba(51,65,85,0.5);
}
</style>
</head>

<body class="text-slate-200">

<div class="max-w-3xl mx-auto px-4 py-10">

    <!-- Title -->
    <h1 class="text-3xl font-800 text-sky-400 mb-3">
        ❓ Help & FAQ
    </h1>

    <p class="text-slate-400 mb-8">
        Welcome to Hungry Wheels support center. Here are answers to the most common questions.
    </p>

    <!-- FAQ LIST -->
    <div class="space-y-4">

        <!-- FAQ -->
        <div class="faq-item pb-4">
            <button onclick="toggleFAQ(this)"
                    class="w-full text-left flex justify-between items-center">

                <span class="font-600">
                    How do I place an order?
                </span>

                <span>+</span>
            </button>

            <div class="hidden mt-2 text-slate-400 text-sm">
                Browse the menu, select your items, customize them,
                then go to your cart and click "Place Order".
            </div>
        </div>

        <!-- FAQ -->
        <div class="faq-item pb-4">
            <button onclick="toggleFAQ(this)"
                    class="w-full text-left flex justify-between items-center">

                <span class="font-600">
                    Can I add notes to my order?
                </span>

                <span>+</span>
            </button>

            <div class="hidden mt-2 text-slate-400 text-sm">
                Yes, you can add notes like "no onions"
                or "extra spicy" before adding the item to your cart.
            </div>
        </div>

        <!-- FAQ -->
        <div class="faq-item pb-4">
            <button onclick="toggleFAQ(this)"
                    class="w-full text-left flex justify-between items-center">

                <span class="font-600">
                    What is Elite membership?
                </span>

                <span>+</span>
            </button>

            <div class="hidden mt-2 text-slate-400 text-sm">
                Elite users get a 10% discount on all orders
                and premium features.
            </div>
        </div>

        <!-- FAQ -->
        <div class="faq-item pb-4">
            <button onclick="toggleFAQ(this)"
                    class="w-full text-left flex justify-between items-center">

                <span class="font-600">
                    How do I track my order?
                </span>

                <span>+</span>
            </button>

            <div class="hidden mt-2 text-slate-400 text-sm">
                After placing your order,
                go to the Orders page to track its status.
            </div>
        </div>

        <!-- FAQ -->
        <div class="faq-item pb-4">
            <button onclick="toggleFAQ(this)"
                    class="w-full text-left flex justify-between items-center">

                <span class="font-600">
                    What payment methods are available?
                </span>

                <span>+</span>
            </button>

            <div class="hidden mt-2 text-slate-400 text-sm">
                Currently we support cash on delivery.
            </div>
        </div>

    </div>

    <!-- CONTACT SECTION -->
    <div class="mt-10 bg-slate-900 border border-slate-800 rounded-2xl p-6">

        <h2 class="text-xl font-700 text-white mb-4">
            💬 Contact Support
        </h2>

        <p class="text-slate-400 text-sm mb-5">
            Still having issues? Contact our support team anytime.
        </p>

        <div class="space-y-3">

            <div class="flex items-center gap-3">
                <span class="text-sky-400 text-lg">📧</span>
                <span class="text-slate-300">
                    support@hungrywheels.com
                </span>
            </div>

            <div class="flex items-center gap-3">
                <span class="text-sky-400 text-lg">📞</span>
                <span class="text-slate-300">
                    0112 334 6565
                </span>
            </div>

            <div class="flex items-center gap-3">
                <span class="text-sky-400 text-lg">🕒</span>
                <span class="text-slate-300">
                    Available daily from 10 AM to 2 AM
                </span>
            </div>

        </div>

    </div>

    <!-- BACK BUTTON -->
    <div class="mt-8">

        <button onclick="history.back()"
                class="flex items-center gap-2 px-4 py-2 rounded-xl
                       border border-slate-700 hover:border-sky-400
                       text-slate-300 hover:text-sky-400 transition text-sm">

            ← Go Back

        </button>

    </div>

</div>

<script>
function toggleFAQ(btn){

    const content = btn.nextElementSibling;
    const icon = btn.querySelector('span:last-child');

    content.classList.toggle('hidden');

    icon.textContent =
        content.classList.contains('hidden') ? '+' : '−';
}
</script>

</body>
</html>