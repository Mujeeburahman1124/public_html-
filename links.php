<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MS Link Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .link-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .link-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .copy-container {
            display: none;
            margin-top: 0.5rem;
        }
        .links-container {
            display: none;
        }
        .links-container.active {
            display: block;
        }
        /* Ensure buttons and inputs scale well on mobile */
        button, input {
            width: 100%;
            box-sizing: border-box;
        }
        /* Adjust font sizes for smaller screens */
        @media (max-width: 640px) {
            h1 {
                font-size: 1.5rem;
            }
            h2 {
                font-size: 1.25rem;
            }
            h3 {
                font-size: 1rem;
            }
            .container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="container mx-auto p-6 sm:p-4">
        <h1 class="text-3xl sm:text-2xl font-bold text-center text-gray-800 mb-8">MS Link  Dashboard</h1>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-4">
            <!-- MSJobs.net Side -->
            <div class="bg-white rounded-lg shadow-lg p-6 sm:p-4">
                <h2 class="text-2xl sm:text-xl font-semibold text-blue-600 mb-4">MSJobs.net</h2>
                <button class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 mb-4 text-sm sm:text-base" onclick="toggleLinks('msjobs-links')">Toggle Links</button>
                <div class="links-container" id="msjobs-links">
                    <div class="space-y-4">
                        <div class="link-card bg-gray-50 p-4 sm:p-3 rounded-md">
                            <h3 class="text-lg sm:text-base font-medium text-gray-700">SuperAdmin</h3>
                            <a href="https://msjobs.net/admin_login" class="text-blue-500 hover:underline text-sm sm:text-base" onclick="showCopyInput(event, this, 'https://msjobs.net/admin_login')">View</a>
                            <div class="copy-container" id="copy-security-guard">
                                <input type="text" class="w-full p-2 border rounded text-sm" readonly>
                                <button class="mt-2 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 text-sm sm:text-base" onclick="copyToClipboard(this.previousElementSibling)">Copy</button>
                            </div>
                        </div>
                        <div class="link-card bg-gray-50 p-4 sm:p-3 rounded-md">
                            <h3 class="text-lg sm:text-base font-medium text-gray-700">Company Login</h3>
                            <a href="https://msjobs.net/login" class="text-blue-500 hover:underline text-sm sm:text-base" onclick="showCopyInput(event, this, 'https://msjobs.net/login')">View</a>
                            <div class="copy-container" id="copy-drivers">
                                <input type="text" class="w-full p-2 border rounded text-sm" readonly>
                                <button class="mt-2 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 text-sm sm:text-base" onclick="copyToClipboard(this.previousElementSibling)">Copy</button>
                            </div>
                        </div>
                        <div class="link-card bg-gray-50 p-4 sm:p-3 rounded-md">
                            <h3 class="text-lg sm:text-base font-medium text-gray-700">Job Approve</h3>
                            <a href="https://msjobs.net/admin-approve" class="text-blue-500 hover:underline text-sm sm:text-base" onclick="showCopyInput(event, this, 'https://msjobs.net/admin-approve')">View</a>
                            <div class="copy-container" id="copy-all-jobs">
                                <input type="text" class="w-full p-2 border rounded text-sm" readonly>
                                <button class="mt-2 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 text-sm sm:text-base" onclick="copyToClipboard(this.previousElementSibling)">Copy</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- MS Human Consultancies Side -->
            <div class="bg-white rounded-lg shadow-lg p-6 sm:p-4">
                <h2 class="text-2xl sm:text-xl font-semibold text-green-600 mb-4">MS Human Consultancies</h2>
                <button class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 mb-4 text-sm sm:text-base" onclick="toggleLinks('consultancies-links')">Toggle Links</button>
                <div class="links-container" id="consultancies-links">
                    <div class="space-y-4">
                        <div class="link-card bg-gray-50 p-4 sm:p-3 rounded-md">
                            <h3 class="text-lg sm:text-base font-medium text-gray-700">Super Admin</h3>
                            <a href="https://msjobs.link/Admin_candidate" class="text-blue-500 hover:underline text-sm sm:text-base" onclick="showCopyInput(event, this, 'https://msjobs.link/Admin_candidate')">Learn More</a>
                            <div class="copy-container" id="copy-recruitment">
                                <input type="text" class="w-full p-2 border rounded text-sm" readonly>
                                <button class="mt-2 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 text-sm sm:text-base" onclick="copyToClipboard(this.previousElementSibling)">Copy</button>
                            </div>
                        </div>
                        <div class="link-card bg-gray-50 p-4 sm:p-3 rounded-md">
                            <h3 class="text-lg sm:text-base font-medium text-gray-700">Company Admin</h3>
                            <a href="https://msjobs.link/CompanyAdminlogin" class="text-blue-500 hover:underline text-sm sm:text-base" onclick="showCopyInput(event, this, 'https://msjobs.link/CompanyAdminlogin')">View</a>
                            <div class="copy-container" id="copy-hr-consulting">
                                <input type="text" class="w-full p-2 border rounded text-sm" readonly>
                                <button class="mt-2 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 text-sm sm:text-base" onclick="copyToClipboard(this.previousElementSibling)">Copy</button>
                            </div>
                        </div>
                        <div class="link-card bg-gray-50 p-4 sm:p-3 rounded-md">
                            <h3 class="text-lg sm:text-base font-medium text-gray-700">Secretary Login</h3>
                            <a href="https://msjobs.link/Rslogin" class="text-blue-500 hover:underline text-sm sm:text-base" onclick="showCopyInput(event, this, 'https://msjobs.link/Rslogin')">View</a>
                            <div class="copy-container" id="copy-secretary">
                                <input type="text" class="w-full p-2 border rounded text-sm" readonly>
                                <button class="mt-2 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 text-sm sm:text-base" onclick="copyToClipboard(this.previousElementSibling)">Copy</button>
                            </div>
                        </div>
                        <div class="link-card bg-gray-50 p-4 sm:p-3 rounded-md">
                            <h3 class="text-lg sm:text-base font-medium text-gray-700">HR Login</h3>
                            <a href="https://msjobs.link/hr-login" class="text-blue-500 hover:underline text-sm sm:text-base" onclick="showCopyInput(event, this, 'https://msjobs.link/hr-login')">View</a>
                            <div class="copy-container" id="copy-training">
                                <input type="text" class="w-full p-2 border rounded text-sm" readonly>
                                <button class="mt-2 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 text-sm sm:text-base" onclick="copyToClipboard(this.previousElementSibling)">Copy</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function toggleLinks(containerId) {
            const container = document.getElementById(containerId);
            container.classList.toggle('active');
        }

        function showCopyInput(event, element, url) {
            event.preventDefault();
            const copyContainer = element.nextElementSibling;
            const input = copyContainer.querySelector('input');
            input.value = url;
            copyContainer.style.display = 'block';
            setTimeout(() => {
                copyContainer.style.display = 'none';
            }, 5000);
        }

        function copyToClipboard(input) {
            input.select();
            try {
                document.execCommand('copy');
                alert('URL copied to clipboard!');
            } catch (err) {
                console.error('Failed to copy: ', err);
                alert('Failed to copy URL. Please try again.');
            }
        }
    </script>
</body>
</html>