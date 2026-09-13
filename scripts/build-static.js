const fs = require("fs");
const path = require("path");

const projectRoot = path.resolve(__dirname, "..");
const publicDir = path.join(projectRoot, "public");
const outputDir = path.join(projectRoot, "static-dist");
const indexTemplate = path.join(projectRoot, "resources", "static", "index.html");
const excludedNames = new Set([
    ".gitignore",
    ".htaccess",
    "index.php",
    "web.config",
]);

function emptyDirectory(directory) {
    if (!fs.existsSync(directory)) {
        fs.mkdirSync(directory, { recursive: true });
        return;
    }

    fs.readdirSync(directory).forEach(name => {
        const target = path.join(directory, name);
        try {
            fs.rmSync(target, { recursive: true, force: true });
        } catch (e) {
            // Ignore if temporarily locked
        }
    });
}

function copyDirectory(source, destination) {
    fs.mkdirSync(destination, { recursive: true });

    fs.readdirSync(source).forEach(name => {
        if (excludedNames.has(name) || name.endsWith(".map")) {
            return;
        }

        const sourcePath = path.join(source, name);
        const destinationPath = path.join(destination, name);
        const stat = fs.lstatSync(sourcePath);

        if (stat.isDirectory()) {
            copyDirectory(sourcePath, destinationPath);
        } else {
            fs.copyFileSync(sourcePath, destinationPath);
        }
    });
}

emptyDirectory(outputDir);
copyDirectory(publicDir, outputDir);
fs.copyFileSync(indexTemplate, path.join(outputDir, "index.html"));

console.log(`Static frontend created at ${outputDir}`);
