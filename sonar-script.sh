#!/usr/bin/env bash
if [ "$1" == "" ]; then
    echo "Repo name missing!";
    exit;
fi

if [ "$2" == "" ]; then
    echo "Repo url missing!";
    exit;
fi

cd ~/Desktop/commits

if [ ! -f "$1.txt" ]; then
    sqlite3 ~/projects/githubgrabber/database/database.sqlite "select commits.sha from commits inner join repos on repos.id = commits.repo_id AND repos.name = '"$1"' order by committed_at ASC;" > "$1.txt"
fi

if [ ! -d "$1" ]; then
    git clone "$2"
fi

cd "$1"

while IFS='' read -r line || [[ -n "$line" ]]; do

    out=$(git checkout "$line" 2>&1)
    find="HEAD is now"
    if echo "$out" | grep -q "$find"; then

        #sonar analysis
        sonar-runner -Dsonar.projectKey="$1:$line" -Dsonar.projectName="$1:$line" -Dsonar.projectVersion="$line" -Dsonar.sources=. -Dsonar.language=php -Dsonar.sourceEncoding=UTF-8

    fi

done < "../$1.txt"