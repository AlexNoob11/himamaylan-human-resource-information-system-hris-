<!-- New Performance Evaluation Modal -->
<div class="modal fade" id="evaluationModal" tabindex="-1" aria-labelledby="evaluationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST">
        <div class="modal-header">
          <h5 class="modal-title" id="evaluationModalLabel">New Performance Evaluation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php
          // Fetch users for dropdown
          $users_res = $conn->query("SELECT id, first_name, last_name, employee_type, department FROM users ORDER BY first_name");
          ?>
          <div class="mb-3">
            <label for="user_id" class="form-label">Select Employee</label>
            <select name="user_id" id="user_id" class="form-select" required>
              <option value="">-- Select Employee --</option>
              <?php while($u = $users_res->fetch_assoc()): ?>
                <option value="<?= $u['id'] ?>">
                  <?= htmlspecialchars($u['first_name'].' '.$u['last_name'].' | '.$u['employee_type'].' | '.$u['department']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="row mb-3">
            <div class="col">
              <label for="review_start" class="form-label">Review Start</label>
              <input type="date" name="review_start" id="review_start" class="form-control" required>
            </div>
            <div class="col">
              <label for="review_end" class="form-label">Review End</label>
              <input type="date" name="review_end" id="review_end" class="form-control" required>
            </div>
          </div>

          <hr>

          <h6>Rate Metrics</h6>
          <?php
          // Fetch metrics
          $metrics_res = $conn->query("SELECT * FROM metrics ORDER BY metric_id");
          while($metric = $metrics_res->fetch_assoc()):
          ?>
          <div class="mb-3 row align-items-center">
            <label class="col-sm-4 col-form-label"><?= htmlspecialchars($metric['name']) ?></label>
            <div class="col-sm-8">
              <select name="metric_<?= $metric['metric_id'] ?>" class="form-select" required>
                <option value="Excellent">Excellent</option>
                <option value="Good">Good</option>
                <option value="Satisfied" selected>Satisfied</option>
                <option value="Poor">Poor</option>
              </select>
            </div>
          </div>
          <?php endwhile; ?>

          <hr>

          <div class="mb-3">
            <label for="strengths" class="form-label">Strengths</label>
            <textarea name="strengths" id="strengths" class="form-control" rows="2"></textarea>
          </div>

          <div class="mb-3">
            <label for="areas_for_improvement" class="form-label">Areas for Improvement</label>
            <textarea name="areas_for_improvement" id="areas_for_improvement" class="form-control" rows="2"></textarea>
          </div>

          <div class="mb-3">
            <label for="goals" class="form-label">Goals</label>
            <textarea name="goals" id="goals" class="form-control" rows="2"></textarea>
          </div>

          <div class="mb-3">
            <label for="reviewer_comments" class="form-label">Reviewer Comments</label>
            <textarea name="reviewer_comments" id="reviewer_comments" class="form-control" rows="2"></textarea>
          </div>

        </div>
        <div class="modal-footer">
          <button type="submit" name="submit_evaluation" class="btn btn-primary">Submit Evaluation</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
