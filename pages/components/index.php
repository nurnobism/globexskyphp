<?php
require_once __DIR__ . '/../../includes/middleware.php';

$pageTitle = 'UI Components';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item active">UI Components</li>
        </ol>
    </nav>

    <h1 class="h2 mb-4"><i class="bi bi-palette me-2"></i>UI Components &amp; Style Guide</h1>

    <!-- Buttons -->
    <section class="mb-5" id="buttons">
        <h3 class="border-bottom pb-2 mb-3"><i class="bi bi-hand-index me-2"></i>Buttons</h3>
        <div class="mb-3">
            <button class="btn btn-primary me-1 mb-2">Primary</button>
            <button class="btn btn-secondary me-1 mb-2">Secondary</button>
            <button class="btn btn-success me-1 mb-2">Success</button>
            <button class="btn btn-danger me-1 mb-2">Danger</button>
            <button class="btn btn-warning me-1 mb-2">Warning</button>
            <button class="btn btn-info me-1 mb-2">Info</button>
            <button class="btn btn-light me-1 mb-2">Light</button>
            <button class="btn btn-dark me-1 mb-2">Dark</button>
        </div>
        <div class="mb-3">
            <button class="btn btn-outline-primary me-1 mb-2">Outline Primary</button>
            <button class="btn btn-outline-secondary me-1 mb-2">Outline Secondary</button>
            <button class="btn btn-outline-success me-1 mb-2">Outline Success</button>
            <button class="btn btn-outline-danger me-1 mb-2">Outline Danger</button>
        </div>
        <div class="mb-3">
            <button class="btn btn-primary btn-lg me-1 mb-2">Large</button>
            <button class="btn btn-primary me-1 mb-2">Default</button>
            <button class="btn btn-primary btn-sm me-1 mb-2">Small</button>
        </div>
        <div>
            <button class="btn btn-primary me-1 mb-2"><i class="bi bi-download me-1"></i>Download</button>
            <button class="btn btn-success me-1 mb-2"><i class="bi bi-check-lg me-1"></i>Save</button>
            <button class="btn btn-danger me-1 mb-2"><i class="bi bi-trash me-1"></i>Delete</button>
            <button class="btn btn-primary me-1 mb-2" disabled>
                <span class="spinner-border spinner-border-sm me-1"></span>Loading...
            </button>
        </div>
    </section>

    <!-- Cards -->
    <section class="mb-5" id="cards">
        <h3 class="border-bottom pb-2 mb-3"><i class="bi bi-card-heading me-2"></i>Cards</h3>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Basic Card</h5>
                        <p class="card-text">A simple card with text content and an action button.</p>
                        <a href="#" class="btn btn-primary btn-sm">Go somewhere</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-primary">
                    <div class="card-header bg-primary text-white"><i class="bi bi-star me-1"></i>Featured</div>
                    <div class="card-body">
                        <h5 class="card-title">Featured Card</h5>
                        <p class="card-text">A highlighted card with a colored header and border.</p>
                    </div>
                    <div class="card-footer text-muted"><small>Last updated 3 mins ago</small></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 shadow">
                    <div class="card-body text-center">
                        <i class="bi bi-graph-up-arrow display-4 text-success mb-3"></i>
                        <h5 class="card-title">Stats Card</h5>
                        <h2 class="fw-bold text-success">$12,450</h2>
                        <p class="text-muted mb-0">Revenue this month</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Badges -->
    <section class="mb-5" id="badges">
        <h3 class="border-bottom pb-2 mb-3"><i class="bi bi-tag me-2"></i>Badges</h3>
        <div class="mb-3">
            <span class="badge bg-primary me-1">Primary</span>
            <span class="badge bg-secondary me-1">Secondary</span>
            <span class="badge bg-success me-1">Success</span>
            <span class="badge bg-danger me-1">Danger</span>
            <span class="badge bg-warning text-dark me-1">Warning</span>
            <span class="badge bg-info text-dark me-1">Info</span>
        </div>
        <div class="mb-3">
            <span class="badge rounded-pill bg-primary me-1">Pill Primary</span>
            <span class="badge rounded-pill bg-success me-1">Pill Success</span>
            <span class="badge rounded-pill bg-danger me-1">99+</span>
        </div>
        <div>
            <button class="btn btn-primary me-2">
                Notifications <span class="badge bg-light text-dark ms-1">4</span>
            </button>
            <button class="btn btn-outline-secondary me-2">
                Messages <span class="badge bg-danger ms-1">12</span>
            </button>
        </div>
    </section>

    <!-- Alerts -->
    <section class="mb-5" id="alerts">
        <h3 class="border-bottom pb-2 mb-3"><i class="bi bi-bell me-2"></i>Alerts</h3>
        <div class="alert alert-primary" role="alert"><i class="bi bi-info-circle me-2"></i>This is a primary alert — check it out!</div>
        <div class="alert alert-success" role="alert"><i class="bi bi-check-circle me-2"></i>Operation completed successfully.</div>
        <div class="alert alert-warning" role="alert"><i class="bi bi-exclamation-triangle me-2"></i>Warning! Please review your input.</div>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-x-octagon me-2"></i>Error! Something went wrong.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </section>

    <!-- Form Elements -->
    <section class="mb-5" id="forms">
        <h3 class="border-bottom pb-2 mb-3"><i class="bi bi-input-cursor-text me-2"></i>Form Elements</h3>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Text Input</label>
                    <input type="text" class="form-control" placeholder="Enter text...">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email with Icon</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" placeholder="email@example.com">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Select</label>
                    <select class="form-select">
                        <option selected>Choose an option...</option>
                        <option>Option One</option>
                        <option>Option Two</option>
                        <option>Option Three</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Textarea</label>
                    <textarea class="form-control" rows="3" placeholder="Enter description..."></textarea>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Checkboxes</label>
                    <div class="form-check"><input class="form-check-input" type="checkbox" checked><label class="form-check-label">Option A</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox"><label class="form-check-label">Option B</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" disabled><label class="form-check-label">Disabled</label></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Radio Buttons</label>
                    <div class="form-check"><input class="form-check-input" type="radio" name="sampleRadio" checked><label class="form-check-label">Choice 1</label></div>
                    <div class="form-check"><input class="form-check-input" type="radio" name="sampleRadio"><label class="form-check-label">Choice 2</label></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Switch</label>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" checked><label class="form-check-label">Toggle feature</label></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Range</label>
                    <input type="range" class="form-range" min="0" max="100" value="50">
                </div>
                <div class="mb-3">
                    <label class="form-label">File Input</label>
                    <input class="form-control" type="file">
                </div>
            </div>
        </div>
    </section>

    <!-- Tables -->
    <section class="mb-5" id="tables">
        <h3 class="border-bottom pb-2 mb-3"><i class="bi bi-table me-2"></i>Tables</h3>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>Widget Pro</td>
                        <td><span class="badge bg-primary">Electronics</span></td>
                        <td>$29.99</td>
                        <td><span class="badge bg-success">Active</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>Gadget X</td>
                        <td><span class="badge bg-info text-dark">Hardware</span></td>
                        <td>$149.00</td>
                        <td><span class="badge bg-warning text-dark">Pending</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>Component Z</td>
                        <td><span class="badge bg-secondary">Parts</span></td>
                        <td>$5.50</td>
                        <td><span class="badge bg-danger">Discontinued</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Pagination -->
    <section class="mb-5" id="pagination">
        <h3 class="border-bottom pb-2 mb-3"><i class="bi bi-three-dots me-2"></i>Pagination</h3>
        <nav>
            <ul class="pagination">
                <li class="page-item disabled"><a class="page-link" href="#"><i class="bi bi-chevron-left"></i></a></li>
                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item"><a class="page-link" href="#">4</a></li>
                <li class="page-item"><a class="page-link" href="#">5</a></li>
                <li class="page-item"><a class="page-link" href="#"><i class="bi bi-chevron-right"></i></a></li>
            </ul>
        </nav>
        <nav>
            <ul class="pagination pagination-sm">
                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
            </ul>
        </nav>
    </section>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
