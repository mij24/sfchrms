    <div class="row">

      <!-- ── LEFT COLUMN ── -->
      <div class="col-md-6">

        <!-- Personal Information (read-only view) -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Personal Information</h3>
            <small class="text-muted">Changes need HR approval</small>
          </div>
          <div class="card-body">
            <div class="section-title">Basic Details</div>
            <div class="row">
              <?php
              $personal_ro = array(
                'Date of birth' => ($emp['date_of_birth'] && $emp['date_of_birth'] !== '0000-00-00')
                                    ? date('F d, Y', strtotime($emp['date_of_birth'])) : '—',
                'Gender'        => $emp['gender'] ?: '—',
                'Civil status'  => $emp['civil_status'] ?: '—',
                'Nationality'   => $emp['nationality'] ?: '—',
              );
              foreach ($personal_ro as $lbl => $val): ?>
              <div class="col-6 mb-3">
                <div class="info-label"><?= $lbl ?></div>
                <div class="info-val"><?= htmlspecialchars($val) ?></div>
              </div>
              <?php endforeach; ?>
            </div>

            <div class="section-title">Contact & Address</div>
            <form method="POST" action="">
              <?php csrf_field(); ?>
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="info-label">Contact number</label>
                    <input type="text" name="contact_number" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($emp['contact_number'] ?: '') ?>">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="info-label">Email address</label>
                    <input type="email" name="email_address" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($emp['email_address'] ?: '') ?>">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="info-label">Civil status</label>
                    <select name="civil_status" class="form-control form-control-sm">
                      <?php foreach (array('Single','Married','Widowed','Separated') as $cs): ?>
                        <option <?= ($emp['civil_status']===$cs)?'selected':'' ?>><?= $cs ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="info-label">Nationality</label>
                    <input type="text" name="nationality" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($emp['nationality'] ?: '') ?>">
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-group">
                    <label class="info-label">Home address</label>
                    <input type="text" name="address" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($emp['address'] ?: '') ?>">
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-group">
                    <label class="info-label">Emergency contact</label>
                    <input type="text" name="emergency_contact" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($emp['emergency_contact'] ?: '') ?>">
                  </div>
                </div>
              </div>
              <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-paper-plane mr-1"></i> Submit for HR Approval
              </button>
            </form>
          </div>
        </div>

        

        <!-- 201 Uploaded Documents -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">201 File — Documents</h3>
            <a href="upload_document.php?id=<?= $emp_id ?>" class="btn btn-xs btn-primary">
              <i class="fas fa-upload mr-1"></i> Upload
            </a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($docs)): ?>
              <p class="text-muted text-center py-3" style="font-size:13px;">No documents uploaded yet.</p>
            <?php else: ?>
            <table class="table table-sm mb-0">
              <thead class="thead-light"><tr><th>Type</th><th>File</th><th>Date</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($docs as $doc): ?>
                <tr>
                  <td><span class="badge badge-secondary" style="font-size:10px;"><?= htmlspecialchars($doc['document_type']) ?></span></td>
                  <td style="font-size:12px;"><?= htmlspecialchars($doc['file_name']) ?></td>
                  <td style="font-size:11px;"><?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></td>
                  <td>
                    <a href="../uploads/<?= htmlspecialchars($doc['file_path']) ?>" target="_blank"
                       class="btn btn-xs btn-info"><i class="fas fa-eye"></i></a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <!-- ── RIGHT COLUMN ── -->
      <div class="col-md-6">
		  
		  <!-- My QR Code -->
        <?php if ($qr): ?>
        <div class="card">
          <div class="card-header"><h3 class="card-title">My DTR QR Code</h3></div>
          <div class="card-body text-center">
            <?php
            $qr_data = urlencode('http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/qr_scanner.php?token='.$qr['qr_token']);
            $qr_url  = 'https://chart.googleapis.com/chart?chs=180x180&cht=qr&chl='.$qr_data.'&choe=UTF-8';
            ?>
            <img src="<?= $qr_url ?>" style="width:150px;height:150px;" alt="My QR Code"><br>
            <small class="text-muted d-block mt-1">Scan this QR at the attendance kiosk to time in/out.</small>
            <a href="<?= $qr_url ?>" download="my_qr_<?= $emp['employee_id'] ?>.png"
               class="btn btn-sm btn-outline-primary mt-2">
              <i class="fas fa-download mr-1"></i> Download QR
            </a>
          </div>
        </div>
        <?php else: ?>
        <div class="card">
          <div class="card-body text-center text-muted py-3" style="font-size:13px;">
            <i class="fas fa-qrcode fa-2x d-block mb-2" style="opacity:.3;"></i>
            No QR code assigned yet. Contact HR to generate your QR code.
          </div>
        </div>
        <?php endif; ?>

        <!-- Employment Details (read-only) -->
        <div class="card">
          <div class="card-header"><h3 class="card-title">Employment Details</h3></div>
          <div class="card-body">
            <div class="row">
              <?php
              $emp_details = array(
                'Employee ID'       => $emp['employee_id'],
                'Department'        => $emp['dept'] ?: '—',
                'Position'          => $emp['position'] ?: '—',
                'Classification'    => $emp['classification_name'] ?: '—',
                'Rank'              => $emp['rank_name'] ?: '—',
                'Employment type'   => $emp['emp_type_name'] ?: $emp['employment_type'] ?: '—',
                'Employment status' => $emp['emp_status_name'] ?: $emp['employment_status'] ?: '—',
                'Direct head'       => $emp['supervisor_name'] ?: '—',
                'Work location'     => $emp['work_location_name'] ?: $emp['work_location'] ?: '—',
                'Date hired'        => ($emp['date_hired'] && $emp['date_hired'] !== '0000-00-00')
                                        ? date('F d, Y', strtotime($emp['date_hired'])) : '—',
                'Salary grade'      => $emp['salary_grade'] ?: '—',
                'Basic salary'      => $emp['basic_salary'] > 0
                                        ? '₱ '.number_format($emp['basic_salary'],2) : '—',
              );
              foreach ($emp_details as $lbl => $val): ?>
              <div class="col-6 mb-3">
                <div class="info-label"><?= $lbl ?></div>
                <div class="info-val"><?= htmlspecialchars($val) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
            <small class="text-muted"><i class="fas fa-lock mr-1"></i>Changed by HR only.</small>
          </div>
        </div>
		  
		  <!-- Government IDs -->
        <div class="card">
          <div class="card-header"><h3 class="card-title">Government IDs</h3></div>
          <div class="card-body">
            <div class="row">
              <?php
              $gov_ids = array(
                'SSS number'        => $emp['sss_number'],
                'PhilHealth number' => $emp['philhealth_number'],
                'Pag-IBIG number'   => $emp['pagibig_number'],
                'TIN number'        => $emp['tin_number'],
              );
              foreach ($gov_ids as $lbl => $val): ?>
              <div class="col-6 mb-3">
                <div class="info-label"><?= $lbl ?></div>
                <div class="info-val"><?= htmlspecialchars($val ?: '—') ?></div>
              </div>
              <?php endforeach; ?>
            </div>
            <small class="text-muted">
              <i class="fas fa-lock mr-1"></i>
              To update government IDs, submit a Profile Change request.
            </small>
          </div>
        </div>

        

        <!-- Recent Attendance / DTR Log -->
        <!--<div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Recent Attendance</h3>
            <a href="my_attendance.php" class="btn btn-xs btn-info">View all</a>
          </div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead class="thead-light">
                <tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Status</th></tr>
              </thead>
              <tbody>
                <?php
                $stat_badge = array(
                    'Present'=>'success','Absent'=>'danger','Late'=>'warning',
                    'Half-day'=>'info','On leave'=>'primary','Holiday'=>'secondary'
                );
                foreach ($att_recent as $a):
                  $sb = isset($stat_badge[$a['status']]) ? $stat_badge[$a['status']] : 'secondary';
                ?>
                <tr>
                  <td style="font-size:12px;"><?= date('M d', strtotime($a['attendance_date'])) ?></td>
                  <td style="font-size:12px;"><?= $a['time_in'] && $a['time_in']!=='00:00:00' ? date('h:i A',strtotime($a['time_in'])) : '—' ?></td>
                  <td style="font-size:12px;"><?= $a['time_out'] && $a['time_out']!=='00:00:00' ? date('h:i A',strtotime($a['time_out'])) : '—' ?></td>
                  <td><span class="badge badge-<?= $sb ?>" style="font-size:10px;"><?= $a['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($att_recent)): ?>
                  <tr><td colspan="4" class="text-center text-muted py-2" style="font-size:12px;">No recent records.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Mobile DTR Log (with photos) -->
        <?php if (!empty($dtr_logs)): ?>
        <!--<div class="card">
          <div class="card-header"><h3 class="card-title">Mobile Clock-In/Out Log</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead class="thead-light">
                <tr><th>Action</th><th>Date & Time</th><th>Photo</th><th>Location</th></tr>
              </thead>
              <tbody>
                <?php foreach ($dtr_logs as $log): ?>
                <tr>
                  <td>
                    <span class="badge badge-<?= $log['action']==='Clock In'?'success':'danger' ?>"
                          style="font-size:10px;">
                      <?= $log['action'] ?>
                    </span>
                  </td>
                  <td style="font-size:11px;"><?= date('M d, g:i A', strtotime($log['log_datetime'])) ?></td>
                  <td>
                    <?php if ($log['photo_path']): ?>
                      <a href="../uploads/mobile_selfie/<?= htmlspecialchars($log['photo_path']) ?>" target="_blank">
                        <img src="../uploads/mobile_selfie/<?= htmlspecialchars($log['photo_path']) ?>"
                             style="width:36px;height:36px;object-fit:cover;border-radius:4px;">
                      </a>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                  </td>
                  <td style="font-size:11px;">
                    <?php if ($log['latitude']): ?>
                      <a href="https://maps.google.com/?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>"
                         target="_blank" class="text-danger">
                        <i class="fas fa-map-marker-alt"></i> Map
                      </a>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>-->

        <!-- Pending requests -->
        <?php if (!empty($pending_requests)): ?>
        <!--<div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Pending Requests</h3>
            <a href="my_requests_history.php" class="btn btn-xs btn-info">View all</a>
          </div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead class="thead-light"><tr><th>Reference</th><th>Type</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($pending_requests as $pr): ?>
                <tr>
                  <td><code style="font-size:10px;"><?= htmlspecialchars($pr['reference_no']) ?></code></td>
                  <td style="font-size:12px;"><?= htmlspecialchars($pr['type_name']) ?></td>
                  <td><span class="badge badge-warning" style="font-size:10px;"><?= $pr['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>-->

      </div>
    </div>