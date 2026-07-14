const { spawn } = require('child_process');

const surge = spawn('npx', ['surge', './', 'reubentech-milk.surge.sh'], {
    cwd: __dirname,
    shell: true
});

surge.stdout.on('data', (data) => {
    const output = data.toString();
    console.log(output);
    if (output.toLowerCase().includes('email:')) {
        surge.stdin.write('reubentechmilk@yopmail.com\n');
    }
    if (output.toLowerCase().includes('password:')) {
        surge.stdin.write('MilkProject2026!\n');
    }
});

surge.stderr.on('data', (data) => {
    console.error(data.toString());
});

surge.on('close', (code) => {
    console.log(`child process exited with code ${code}`);
});
