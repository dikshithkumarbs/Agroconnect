<?php
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('farmer');

// Get all articles
$stmt = $conn->prepare("SELECT a.*, CASE WHEN a.author_id IS NOT NULL THEN CONCAT(e.name, ' (Expert)') ELSE 'Agro Connect' END as author_name FROM articles a LEFT JOIN experts e ON a.author_id = e.id ORDER BY a.created_at DESC");
$stmt->execute();
$articles = $stmt->get_result();

include '../includes/header.php';
?>

<div class="container">
    <h2>Knowledge Center</h2>

    <div class="card">
        <h3>Agricultural Articles & Guides</h3>
        <?php if ($articles->num_rows > 0): ?>
            <div class="equipment-grid">
                <?php while ($article = $articles->fetch_assoc()): ?>
                    <div class="equipment-card">
                        <div class="content">
                            <h4><?php echo $article['title']; ?></h4>
                            <p><strong>Category:</strong> <?php echo ucfirst($article['category']); ?></p>
                            <p><strong>Author:</strong> <?php echo $article['author_name'] ?: 'Agro Connect'; ?></p>
                            <p><?php echo substr($article['content'], 0, 150); ?>...</p>
                            <button onclick="toggleVisibility('article_<?php echo $article['id']; ?>')" class="btn">Read More</button>
                        </div>
                    </div>
                    <div id="article_<?php echo $article['id']; ?>" style="display: none;" class="card">
                        <h4><?php echo $article['title']; ?></h4>
                        <p><em>By <?php echo $article['author_name'] ?: 'Agro Connect'; ?> on <?php echo date('M d, Y', strtotime($article['created_at'])); ?></em></p>
                        <div><?php echo nl2br($article['content']); ?></div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p>No articles available at the moment.</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
