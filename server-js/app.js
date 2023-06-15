const express = require("express");
const bodyParser = require("body-parser");
const cors = require("cors");

const app = express();

app.use(cors());
app.use(bodyParser.json());
let postData = "";

app.get("/api", (req, res) => {
  res.send(postData);
});

app.post("/api", (req, res) => {
  const data = req.body;
  console.log(data);

  postData = data;

  res.send("Data received successfully.");
});
app.delete("/api/delete", (req, res) => {
  postData = "";

  res.json({ message: "Data deleted from Server 2." } + postData);
});
app.listen(4000, () => {
  console.log("Server listening on port 4000");
});
