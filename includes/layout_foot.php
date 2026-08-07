  </main>
</div>

<!-- Custom Delete Modal -->
<div id="customDeleteModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center">
  <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm cursor-pointer" id="customDeleteOverlay"></div>
  <div class="relative bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm mx-4 transform transition-all">
    <h3 class="text-lg font-bold text-gray-900 mb-2">Confirm Deletion</h3>
    <p class="text-sm text-gray-500 mb-6">Are you sure you want to delete this record? This action cannot be undone.</p>
    <div class="flex justify-end gap-3">
      <button type="button" id="customDeleteCancel" class="rounded-full border border-gray-300 bg-white text-sm font-medium px-4 py-2 text-gray-700 hover:bg-gray-50">Cancel</button>
      <button type="button" id="customDeleteConfirm" class="rounded-full bg-[#173B32] hover:bg-[#173B32]/90 text-white text-sm font-medium px-4 py-2">Delete</button>
    </div>
  </div>
</div>

<script src="../assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
