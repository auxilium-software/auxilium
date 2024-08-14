class InfiniteScroller {
    #loadingIndicator;
    #target;
    #currentPage = 0;
    #queryResponseId = null;
    
    #spawnLoadingIndicator() {
        this.#loadingIndicator = document.createElement("div");
        this.#loadingIndicator.classList.add("logical-box");
        
        let indicatorBox = document.createElement("div");
        indicatorBox.classList.add("loading-placeholder");
        indicatorBox.classList.add("large-spacer");
        this.#loadingIndicator.appendChild(indicatorBox);
        
        this.#target.appendChild(this.#loadingIndicator);
    }
    
    #loadMore(forced = false) {
        let bounds = this.#target.getBoundingClientRect();
        let tryLoadMore = false;
        if (bounds.bottom < (window.innerHeight * 2)) {
            tryLoadMore = true;
        }
        if (forced) {
            tryLoadMore = true;
        }
        //console.log("Load more? " + (tryLoadMore ? "YES" : "NO"));
        if (tryLoadMore) {
            let http = new XMLHttpRequest();
            
            let url = "/api/v2/query";
            
            this.#currentPage++;
            
            let data = new FormData();
            data.append("query", this.#queryResponseId);
            data.append("paginate", true);
            data.append("page_size", 8);
            data.append("page", this.#currentPage);
            
            this.#loadingIndicator.style.display = null;

            http.open("POST", url, true);
            http.send(data);

            let responseHad = false;
            
            http.onreadystatechange = (e) => {
                if (http.readyState == 4 && !responseHad) {
                    let response = {};
                    responseHad = true;
                    
                    try {
                        response = JSON.parse(http.responseText);
                        let result = response["result_slice"];
                        //console.log(result);
                        
                        let nodes = [];
                        
                        if (result.hasOwnProperty("@rows")) {
                            for (let rowId in result["@rows"]) {
                                //console.log(rowId);
                                if (result["@rows"].hasOwnProperty(rowId)) {
                                    for (let key in result["@rows"][rowId]) {
                                        if (key == "@path") {
                                            for (let propId in result["@rows"][rowId]["@path"]) {
                                                if (result["@rows"][rowId]["@path"].hasOwnProperty(propId)) {
                                                    nodes.push(result["@rows"][rowId]["@path"][propId]);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        for (let i = 0; i < nodes.length; i++) {
                            //console.log(nodes[i]);
                            let nodeView = new InlineNodeView(nodes[i]);
                            this.#target.insertBefore(nodeView.render(), this.#loadingIndicator);
                        }
                        
                        this.#loadingIndicator.style.display = "none";
                        
                        if (nodes.length > 0) { // If this returned nothing, we're done loading! The "infinite" scroll has ended.
                            console.log("End of list");
                            this.#loadMore();
                        }
                    } catch(e) {
                        console.log(e);
                        console.log(http.responseText);
                    }
                }
            }
        } else {
            setTimeout(() => {
                this.#loadMore();
            }, 500);
        }
    }
    
    constructor(query, target, compact = false) {
        this.#target = target;
        this.#spawnLoadingIndicator();
        
        let http = new XMLHttpRequest();
        let url = "/api/v2/query";
        
        let data = new FormData();
        data.append("query", query);
        data.append("paginate", true);
        data.append("page_size", 8);

        http.open("POST", url, true);
        http.send(data);

        let responseHad = false;
        
        http.onreadystatechange = (e) => {
            if (http.readyState == 4 && !responseHad) {
                let response = {};
                responseHad = true;
                
                try {
                    response = JSON.parse(http.responseText);
                    let result = response["result_slice"];
                    this.#queryResponseId = result["@generated_query"];
                    //console.log(result);
                    
                    let nodes = [];
                    
                    if (result.hasOwnProperty("@rows")) {
                        for (let rowId = 0; rowId < result["@rows"].length; rowId++) {
                            for (let key in result["@rows"][rowId]) {
                                if (key == "@path") {
                                    for (let propId in result["@rows"][rowId]["@path"]) {
                                        if (result["@rows"][rowId]["@path"].hasOwnProperty(propId)) {
                                            nodes.push(result["@rows"][rowId]["@path"][propId]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    for (let i = 0; i < nodes.length; i++) {
                        let nodeView = compact ? new PathNameView(nodes[i], true) : new InlineNodeView(nodes[i]);
                        let nodeViewRender = nodeView.render();
                        this.#target.insertBefore(nodeViewRender, this.#loadingIndicator);
                    }
                    
                    this.#loadingIndicator.style.display = "none";
                    
                    this.#loadMore();
                } catch(e) {
                    console.log(e);
                    console.log(http.responseText);
                }
            }
        }
        
        let path = query.slice(query.indexOf("FROM ") + 6);
        path = path.split(" ");
        path = path[0];
        path = path.split("/");
        path.pop();
        path = path.join("/");
        
        //console.log("Adding listener on deriv " + path)
        
        default_auxilium_client.getNodes(path.endsWith("/") ? path.slice(0, -1) : path).then((nodes) => {
            for (const [path, node] of Object.entries(nodes)) {
                //console.log("Adding listener on " + path)
                node.addEventListener("change", () => {
                    console.log("Reflowing infinite scroller! " + path)
                    this.#target.innerHTML = "";
                    this.#spawnLoadingIndicator();
                    this.#currentPage = -1;
                    this.#loadMore(true);
                });
            }
        });
        
    }
}
