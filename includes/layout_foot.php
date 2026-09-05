  </main>
  </div>
</div>

<!-- Custom Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-[200] hidden items-center justify-center">
  <div id="deleteModalOverlay" class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
  <div id="deleteModalBox"
       class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6
              transform transition-all duration-200 scale-95 opacity-0">
    <!-- Icon -->
    <div class="flex items-center justify-center w-12 h-12 rounded-full bg-red-100 mx-auto mb-4">
      <i class="ti ti-trash text-red-600 text-2xl"></i>
    </div>
    <!-- Text -->
    <h3 id="deleteModalTitle" class="text-lg font-bold text-gray-900 text-center mb-1">Confirm Deletion</h3>
    <p  id="deleteModalBody"  class="text-sm text-gray-500 text-center mb-6">Are you sure? This action cannot be undone.</p>
    <!-- Buttons -->
    <div class="flex gap-3">
      <button id="deleteModalCancel"
              class="flex-1 rounded-full border border-gray-300 bg-white text-sm font-medium py-2.5 text-gray-700 hover:bg-gray-50 transition-colors">
        Cancel
      </button>
      <button id="deleteModalConfirm"
              class="flex-1 rounded-full bg-red-600 hover:bg-red-700 text-white text-sm font-semibold py-2.5 transition-colors flex items-center justify-center gap-2">
        <i class="ti ti-trash text-sm"></i> Delete
      </button>
    </div>
  </div>
</div>

<!-- Toast notifications -->
<div id="toastContainer" class="fixed bottom-5 right-5 z-[300] flex flex-col gap-2 pointer-events-none"></div>

<script src="../assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
