<?php
// ============================================
// FILE: includes/footer.php — UPDATED
// Adds DataTables Buttons (Excel/CSV/Print) globally
// All pages that include this file get export buttons
// ============================================
?>
  <footer class="main-footer">
    <strong>HRMS</strong> &copy; <?= date('Y') ?> &mdash; Human Resource Management System.
    <div class="float-right d-none d-sm-inline-block"><b>Version</b> 1.0</div>
  </footer>
</div><!-- /.wrapper -->

<!-- Core JS -->
<script src="../assets/plugins/jquery/jquery.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- DataTables -->
<script src="../assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>

<!-- DataTables Buttons (Excel / CSV / Print) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables-buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables-buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables-buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables-buttons/2.4.1/js/buttons.print.min.js"></script>

<!-- AdminLTE -->
<script src="../assets/dist/js/adminlte.min.js"></script>

<!-- Global DataTable default — adds Export buttons to every table with class .dt-export -->
<script>
$(function(){
    $('.dt-export').DataTable({
        pageLength: 25,
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel mr-1"></i> Excel',
                className: 'btn btn-success btn-sm',
                exportOptions: { columns: ':not(:last-child)' }
            },
            {
                extend: 'csvHtml5',
                text: '<i class="fas fa-file-csv mr-1"></i> CSV',
                className: 'btn btn-info btn-sm',
                exportOptions: { columns: ':not(:last-child)' }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print mr-1"></i> Print',
                className: 'btn btn-secondary btn-sm',
                exportOptions: { columns: ':not(:last-child)' }
            }
        ]
    });
});
</script>