const fs = require("fs");
const path = require("path");
const { execSync } = require("child_process");
const { createHash } = require("crypto");

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

// Generate version metadata dynamically from git
let commit = "fce6a60";
let shortCommit = "fce6a60";
let branch = "feature/himoto-complete-integration";
try {
    commit = execSync("git rev-parse HEAD", { cwd: projectRoot }).toString().trim();
    shortCommit = execSync("git rev-parse --short HEAD", { cwd: projectRoot }).toString().trim();
    branch = execSync("git branch --show-current", { cwd: projectRoot }).toString().trim();
} catch (e) {}

const versionData = {
    name: "himoto-fleet-dashboard",
    version: "1.1.0",
    commit: commit,
    short_commit: shortCommit,
    branch: branch,
    build_time: new Date().toISOString(),
    environment: "production"
};

const versionJson = JSON.stringify(versionData, null, 2);
fs.writeFileSync(path.join(projectRoot, "resources", "js", "src", "version.json"), versionJson, "utf8");

emptyDirectory(outputDir);
copyDirectory(publicDir, outputDir);
// Fingerprint the startup assets too. The HTML always selects the current
// build, while a browser can reuse unchanged code/styles on subsequent visits.
let indexHtml = fs.readFileSync(indexTemplate, "utf8");
const startupAssets = ["/js/app.js", "/css/app.css", "/css/himoto-app.css", "/css/element-ui/index.css"];
for (const asset of startupAssets) {
    const source = path.join(outputDir, asset.slice(1));
    const content = fs.readFileSync(source);
    const hash = createHash("sha256").update(content).digest("hex").slice(0, 16);
    const extension = path.extname(asset);
    const versioned = asset.slice(0, -extension.length) + "." + hash + extension;
    fs.writeFileSync(path.join(outputDir, versioned.slice(1)), content);
    indexHtml = indexHtml.split(asset).join(versioned);
}
fs.writeFileSync(path.join(outputDir, "index.html"), indexHtml);

// Write public and static-dist version.json artifacts
fs.writeFileSync(path.join(publicDir, "version.json"), versionJson, "utf8");
fs.writeFileSync(path.join(outputDir, "version.json"), versionJson, "utf8");

console.log(`Static frontend created at ${outputDir} (Commit: ${shortCommit})`);
