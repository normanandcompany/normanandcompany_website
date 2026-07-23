<!-- ========================================= -->
<!-- TASK MANAGEMENT PAGE -->
<!-- File: /admin/pages/tasks.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- Written by: Joe Leone -->
<!-- ========================================= -->

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('admin');
?>

<section class="product-manager task-manager" data-current-user-id="<?php echo htmlspecialchars((string) getUserId(), ENT_QUOTES, 'UTF-8'); ?>">

    <div id="page-title-meta"
        data-title="Norman and Company | Task Management"
        style="display: none;">
    </div>

    <div class="product-manager-header">
        <div>
            <h1>Task Management</h1>
        </div>

        <button type="button" class="btn-primary product-add-button" id="addTaskBtn">
            Add Task
        </button>
    </div>

    <div class="product-toolbar task-toolbar" aria-label="Task filters">
        <div class="product-search-field">
            <label for="taskSearchInput">Search</label>
            <input
                type="search"
                id="taskSearchInput"
                placeholder="Task, description, or user"
                autocomplete="off"
            >
        </div>

        <div class="product-filter-field">
            <label for="taskUserFilter">User</label>
            <select id="taskUserFilter">
                <option value="all">All Users</option>
            </select>
        </div>

        <div class="product-filter-field">
            <label for="taskStatusFilter">Status</label>
            <select id="taskStatusFilter">
                <option value="all">All Statuses</option>
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="canceled">Canceled</option>
            </select>
        </div>

        <div class="product-filter-field">
            <label for="taskPriorityFilter">Priority</label>
            <select id="taskPriorityFilter">
                <option value="all">All Priorities</option>
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
            </select>
        </div>

        <button type="button" class="btn-secondary product-filter-reset" id="resetTaskFiltersBtn">
            Reset
        </button>
    </div>

    <div class="product-metrics task-metrics" aria-label="Task summary">
        <div class="product-metric">
            <span id="taskTotalCount">0</span>
            <small>Total</small>
        </div>
        <div class="product-metric">
            <span id="taskOpenCount">0</span>
            <small>Open</small>
        </div>
        <div class="product-metric">
            <span id="taskOverdueCount">0</span>
            <small>Overdue</small>
        </div>
        <div class="product-metric">
            <span id="taskFilteredCount">0</span>
            <small>Shown</small>
        </div>
    </div>

    <div id="taskAlert" class="product-alert" role="status" aria-live="polite" hidden></div>

    <div class="product-table-panel">
        <div class="product-table-scroll">
            <table class="product-table task-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Assigned To</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Due</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tasksTableBody">
                    <tr>
                        <td colspan="7" class="product-empty-state">Loading tasks...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="product-pagination">
            <button type="button" id="taskPrevPageBtn" class="btn-secondary">
                Previous
            </button>

            <span id="taskPageInfo">
                Page 1
            </span>

            <button type="button" id="taskNextPageBtn" class="btn-secondary">
                Next
            </button>
        </div>
    </div>

    <dialog id="taskFormDialog" class="product-dialog task-dialog">
        <form id="taskForm" class="product-form">
            <input type="hidden" id="taskId" name="id">

            <div class="product-dialog-header">
                <div>
                    <p class="section-kicker">Task Details</p>
                    <h2 id="taskFormTitle">Add Task</h2>
                </div>

                <button type="button" class="product-dialog-close" id="closeTaskDialogBtn" aria-label="Close">
                    &times;
                </button>
            </div>

            <div class="product-form-grid task-form-grid">
                <div class="product-form-main">
                    <div class="form-group stacked">
                        <label for="taskTitle">Title</label>
                        <input
                            type="text"
                            id="taskTitle"
                            name="title"
                            maxlength="255"
                            required
                        >
                    </div>

                    <div class="product-field-row two-column">
                        <div class="form-group stacked">
                            <label for="taskUserId">Assigned User</label>
                            <select id="taskUserId" name="user_id" required>
                                <option value="">Select user</option>
                            </select>
                        </div>

                        <div class="form-group stacked">
                            <label for="taskDueAt">Due Date</label>
                            <input
                                type="datetime-local"
                                id="taskDueAt"
                                name="due_at"
                            >
                        </div>
                    </div>

                    <div class="product-field-row two-column">
                        <div class="form-group stacked">
                            <label for="taskStatus">Status</label>
                            <select id="taskStatus" name="task_status" required>
                                <option value="open">Open</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="canceled">Canceled</option>
                            </select>
                        </div>

                        <div class="form-group stacked">
                            <label for="taskPriority">Priority</label>
                            <select id="taskPriority" name="priority" required>
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>

                    <div class="task-recurrence-panel">
                        <label class="toggle-row">
                            <input type="checkbox" id="taskRecurring" name="is_recurring" value="1">
                            <span>Recurring Task</span>
                        </label>

                        <div class="task-recurrence-fields" id="taskRecurrenceFields" hidden>
                            <div class="product-field-row three-column">
                                <div class="form-group stacked">
                                    <label for="taskRecurrenceFrequency">Repeat</label>
                                    <select id="taskRecurrenceFrequency" name="recurrence_frequency">
                                        <option value="daily">Daily</option>
                                        <option value="weekly" selected>Weekly</option>
                                        <option value="monthly">Monthly</option>
                                        <option value="yearly">Yearly</option>
                                    </select>
                                </div>

                                <div class="form-group stacked">
                                    <label for="taskRecurrenceInterval">Every</label>
                                    <input
                                        type="number"
                                        id="taskRecurrenceInterval"
                                        name="recurrence_interval"
                                        min="1"
                                        max="12"
                                        value="1"
                                    >
                                </div>

                                <div class="form-group stacked">
                                    <label for="taskRecurrenceCount">Occurrences</label>
                                    <input
                                        type="number"
                                        id="taskRecurrenceCount"
                                        name="recurrence_count"
                                        min="2"
                                        max="52"
                                        value="2"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group stacked">
                        <label for="taskDescription">Description</label>
                        <textarea
                            id="taskDescription"
                            name="description"
                            rows="8"></textarea>
                    </div>
                </div>

                <aside class="product-form-side">
                    <div class="task-preview-card">
                        <span class="status-badge role-badge" id="taskPreviewStatus">Open</span>
                        <strong id="taskPreviewTitle">New Task</strong>
                        <span id="taskPreviewUser">No user selected</span>
                        <small id="taskPreviewDue">No due date</small>
                        <small id="taskPreviewRecurrence">One-time task</small>
                    </div>
                </aside>
            </div>

            <div class="product-dialog-actions">
                <button type="button" class="btn-secondary" id="cancelTaskBtn">
                    Cancel
                </button>

                <button type="submit" class="btn-primary" id="saveTaskBtn">
                    Save Task
                </button>
            </div>
        </form>
    </dialog>
</section>
